<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Breaker;
use App\Models\JournalEntry;
use App\Models\LedgerBalance;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerCurrencyController extends Controller
{
    use ControllerResultHandler;

    /**
     * Adding a currency to the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function add(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            [$success, $parsed] = $this->parseRequest($request->input());
            if (!$success) {
                $this->errors = $parsed;
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }
            // check for duplicate
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            if (LedgerCurrency::where('code', $parsed['code'])->first() !== null) {
                $this->errors[] = __(
                    "Account :code already exists.",
                    ['code' => $parsed['code']]
                );
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }

            $ledgerCurrency = new LedgerCurrency();
            $ledgerCurrency->code = $parsed['code'];
            $ledgerCurrency->decimals = $parsed['decimals'];
            $ledgerCurrency->save();
            $ledgerCurrency->refresh();
            $this->success($request);
            $response['currency'] = $ledgerCurrency->toResponse();
        } catch (Breaker $exception) {
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }

    /**
     * Delete a currency. The currency must be unused.
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            $input = $request->all();
            if ($input['code'] ?? false) {
                $currencyCode = strtoupper($input['code']);
            } else {
                $this->errors[] = __('the code property is required');
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $ledgerCurrency = LedgerCurrency::find($currencyCode);
            if ($ledgerCurrency === null) {
                $this->errors[] = __(
                    'currency :code does not exist',
                    ['code' => $currencyCode]
                );
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }
            // Ensure there are no journal entries that use this currency
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $used = JournalEntry::where('currency', $currencyCode)->count();
            if ($used !== 0) {
                $this->errors[] = __(
                    "Can't delete: transactions use the :code currency.",
                    ['code' => $currencyCode]
                );
            }
            // Ensure there are no balances with this currency
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $used = LedgerBalance::where('currency', $currencyCode)->count();
            if ($used !== 0) {
                $this->errors[] = __(
                    "Can't delete: ledger accounts use the :code currency.",
                    ['code' => $currencyCode]
                );
            }
            // Ensure there are no domains using this currency as default
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $used = LedgerDomain::where('currencyDefault', $currencyCode)->count();
            if ($used !== 0) {
                $this->errors[] = __(
                    "Can't delete: ledger domains use the :code currency.",
                    ['code' => $currencyCode]
                );
            }
            if (count($this->errors)) {
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }
            $ledgerCurrency->delete();
            $this->success($request);
            $response['currency'] = (object)['code' => $currencyCode];
        } catch (Breaker $exception) {
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }
        $response['time'] = new Carbon();

        return $response;
    }

    /**
     * @param string $currencyCode
     * @return LedgerCurrency
     * @throws Breaker
     */
    private function fetch(string $currencyCode): LedgerCurrency
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerCurrency = LedgerCurrency::find($currencyCode);
        if ($ledgerCurrency === null) {
            $this->errors[] = __(
                'currency :code does not exist',
                ['code' => $currencyCode]
            );
            throw Breaker::fromCode(Breaker::INVALID_OPERATION);
        }

        return $ledgerCurrency;
    }

    /**
     * Fetch a currency.
     *
     * @param Request $request
     * @return array
     */
    public function get(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            $input = $request->all();
            if ($input['code'] ?? false) {
                $currencyCode = strtoupper($input['code']);
            } else {
                $this->errors[] = __('the code property is required');
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }
            $ledgerCurrency = $this->fetch($currencyCode);
            $this->success($request);
            $response['currency'] = $ledgerCurrency->toResponse();
        } catch (Breaker $exception) {
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }
        $response['time'] = new Carbon();

        return $response;
    }

    /**
     * Validate request data to define a currency.
     *
     * @param array $data Request data
     * @param string $mode One of add|update, determines what to check.
     * @return array [bool, array] On success the boolean is true and the array contains
     * valid currency data. On failure, the boolean is false and the array is a list of
     * error messages.
     */
    public static function parseRequest(array $data, $mode = 'add'): array
    {
        $errors = [];
        $result = [];
        $status = true;

        if (!($data['code'] ?? false)) {
            $errors[] = __('the code property is required');
            $status = false;
        } else {
            $result['code'] = strtoupper($data['code']);
        }

        $hasDecimals = isset($data['decimals']);
        $decimalsIsNumeric = $hasDecimals && is_numeric($data['decimals']);
        if ($decimalsIsNumeric) {
            $result['decimals'] = (int) $data['decimals'];
        } else {
            if ($mode === 'add') {
                $errors[] = __('a numeric decimals property is required');
                $status = false;
            } elseif ($hasDecimals) {
                $errors[] = __('decimals property must be numeric');
                $status = false;
            }
        }
        if ($mode === 'update') {
            if (!($data['revision'] ?? false)) {
                $errors[] = __('the revision property is required');
                $status = false;
            } else {
                $result['revision'] = $data['revision'];
            }
            if ($data['toCode'] ?? false) {
                $result['toCode'] = strtoupper($data['toCode']);
            }
        }
        if ($status) {
            return [true, $result];
        }

        return [false, $errors];
    }

    /**
     * Update a currency.
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request): array
    {
        $response = [];
        $this->errors = [];
        $inTransaction = false;
        try {
            $input = $request->all();
            [$success, $parsed] = $this->parseRequest($input, 'update');
            if (!$success) {
                $this->errors = $parsed;
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }
            $ledgerCurrency = $this->fetch($parsed['code']);
            $ledgerCurrency->checkRevision($input['revision'] ?? null);

            if (isset($parsed['decimals'])) {
                if ($parsed['decimals'] < $ledgerCurrency->decimals) {
                    /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                    $used = JournalEntry::where('currency', $ledgerCurrency->code)->count();
                    if ($used !== 0) {
                        $this->errors[] = __(
                            "Can't decrease the decimal size of a currency in use."
                        );
                        throw Breaker::fromCode(Breaker::INVALID_OPERATION);
                    }
                }
                $ledgerCurrency->decimals = $parsed['decimals'];
            }
            $codeChange = isset($parsed['toCode'])
                && $ledgerCurrency->code !== $parsed['toCode'];
            if ($codeChange) {
                $ledgerCurrency->code = $parsed['toCode'];
            }

            DB::beginTransaction();
            $inTransaction = true;
            if ($codeChange) {
                // Update all transactions that use the currency
                JournalEntry::where('currency', $parsed['code'])
                    ->update(['currency' => $parsed['toCode']]);
                // Update all balances
                LedgerBalance::where('currency', $parsed['code'])
                    ->update(['currency' => $parsed['toCode']]);
                // Update all domains
                LedgerDomain::where('currencyDefault', $parsed['code'])
                    ->update(['currencyDefault' => $parsed['toCode']]);
            }
            $ledgerCurrency->save();
            DB::commit();
            $inTransaction = false;
            $ledgerCurrency->refresh();
            $this->success($request);
            $response['currency'] = $ledgerCurrency->toResponse();
        } catch (Breaker $exception) {
            if ($exception->getCode() === Breaker::BAD_REVISION) {
                $this->errors[] = $exception->getMessage();
            }
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }

        if ($inTransaction) {
            DB::rollBack();
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }
        $response['time'] = new Carbon();

        return $response;
    }

}
