<?php
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\LedgerBalance;
use App\Models\LedgerName;
use App\Traits\ControllerExceptionHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class LedgerAccountController extends Controller
{
    use ControllerExceptionHandler;

    protected array $errors = [];

    protected stdClass $rules;

    /**
     * @param array $messages
     * @throws Breaker
     */
    protected function badRequest(array $messages)
    {
        $this->errors[] = __(
            "Errors in request: " . implode(', ', $messages) . "."
        );
        throw Breaker::fromCode(Breaker::BAD_REQUEST);
    }

    /**
     * Delete a ledger account (and all sub-accounts). The accounts must be unused.
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $response = [];
        $this->errors = [];
        $inTransaction = false;
        try {
            $ledgerAccount = $this->fetchAccount($request->all());
            // Ensure there are no sub-accounts with associated transactions
            $relatedAccounts = $this->getSubAccountList($ledgerAccount->ledgerUuid);
            $accountTable = (new LedgerAccount())->getTable();
            $balanceTable = (new LedgerBalance())->getTable();
            $subCats = DB::table($accountTable)
                ->join($balanceTable,
                    $accountTable . '.ledgerUuid', '=',
                    $balanceTable . '.ledgerUuid'
                )
                ->whereIn($accountTable . '.ledgerUuid', $relatedAccounts)
                ->count();
            if ($subCats !== 0) {
                $this->errors[] = __("Can't delete: account or sub-accounts have transactions.");
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }
            $nameTable = (new LedgerName())->getTable();
            DB::beginTransaction();
            $inTransaction = true;
            Db::table($balanceTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            Db::table($nameTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            Db::table($accountTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            DB::commit();
            $inTransaction = false;
            $this->success($request);
            $response['accounts'] = $relatedAccounts;
        } catch (Breaker $exception) {
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

    /**
     * @param array $input
     * @return LedgerAccount
     * @throws Breaker
     */
    protected function fetchAccount(array $input): LedgerAccount
    {
        if (!isset($input['uuid']) && !isset($input['code'])) {
            $this->errors[] = __(
                "Request requires either code or uuid.",
            );
            throw Breaker::fromCode(Breaker::BAD_REQUEST);
        }

        // Preference to UUID over code
        if (isset($input['uuid'])) {
            $uuid = $input['uuid'];
            $ledgerAccount = LedgerAccount::find($uuid);
            if ($ledgerAccount === null) {
                $this->errors[] = __(
                    "Account with uuid :uuid not found.",
                    ['uuid' => $uuid]
                );
                throw Breaker::fromCode(Breaker::BAD_ACCOUNT);
            }
            if (isset($input['code'])) {
                $code = $input['code'];
                if ($ledgerAccount->code !== $code) {
                    $this->errors[] = __(
                        "Account with :uuid does not match requested code :code.",
                        ['code' => $code, 'uuid' => $uuid]
                    );
                    throw Breaker::fromCode(Breaker::BAD_ACCOUNT);
                }
            }
        } else {
            $code = $input['code'];
            $ledgerAccount = LedgerAccount::where('code', $code)->first();
            if ($ledgerAccount === null) {
                $this->errors[] = __(
                    "Account with code :code not found.",
                    ['code' => $code]
                );
                throw Breaker::fromCode(Breaker::BAD_ACCOUNT);
            }
        }

        return $ledgerAccount;
    }

    /**
     * Fetch a single account
     *
     * @param Request $request
     * @return array
     */
    public function get(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            $ledgerAccount = $this->fetchAccount($request->all());
            $response['account'] = $ledgerAccount->toResponse();
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

    protected function getSubAccountList(string $ledgerUuid): array
    {
        $idList = [$ledgerUuid];
        $subAccounts = LedgerAccount::where('parentUuid', $ledgerUuid)->get();
        /** @var LedgerAccount $account */
        foreach ($subAccounts as $account) {
            $idList = array_merge($idList, $this->getSubAccountList($account->ledgerUuid));
        }

        return $idList;
    }

    /**
     * Validate request data to define an account other than the root.
     *
     * @param array $data Request data
     * @param stdClass|null $rules Ledger rules.
     * @return array [bool, array] On success the boolean is true and the array contains
     * valid account data. On failure, the boolean is false and the array is a list of
     * error messages.
     */
    public static function parseCreateRequest(array $data, stdClass $rules = null): array
    {
        $errors = [];
        $result = [];
        $status = true;

        $format = $rules->account->codeFormat ?? '';
        if (!($data['code'] ?? false)) {
            $errors[] = 'the code property is required';
            $status = false;
        } else {
            if ($format !== '') {
                if (preg_match($format, $data['code'])) {
                    $result['code'] = $data['code'];
                } else {
                    $status = false;
                    $errors[] = "account code must match the form $format";
                }
            }
        }
        [$subStatus, $names] = LedgerNameController::parseRequestList(
            $data['names'] ?? [], true, 1
        );
        if ($subStatus) {
            $result['names'] = $names;
        } else {
            $status = false;
            $errors = array_merge($errors, $names);
        }
        if (isset($data['parent'])) {
            [$subStatus, $parent] = self::parseParent($data['parent'], $format);
            if ($subStatus) {
                $result['parent'] = $parent;
            } else {
                $status = false;
                $errors = array_merge($errors, $parent);
            }
        }
        $result['category'] = $data['category'] ?? false;
        $result['closed'] = $data['closed'] ?? false;
        $result['credit'] = $data['credit'] ?? false;
        $result['debit'] = $data['debit'] ?? false;
        if ($result['credit'] && $result['debit']) {
            $status = false;
            $errors[] = "account cannot be both debit and credit";
        }

        return [$status, $status ? $result : $errors];
    }

    protected static function parseParent(array $data, string $format): array
    {
        $errors = [];
        $result = [];
        $status = true;
        if (isset($data['code'])) {
            $result['code'] = $data['code'];
        }
        if (isset($data['uuid'])) {
            $result['uuid'] = $data['uuid'];
        }
        if (count($result) === 0) {
            $status = false;
            $errors[] = 'parent must include at least one of code or uuid';
        }
        if (isset($result['code']) && $format !== '') {
            if (!preg_match($format, $result['code'])) {
                $status = false;
                $errors[] = "account code must match the form $format";
            }
        }

        return [$status, $status ? $result : $errors];
    }

    /**
     * Validate request data to update an account.
     *
     * @param array $data Request data
     * @param stdClass|null $rules Ledger rules.
     * @return array [bool, array] On success the boolean is true and the array contains
     * valid account data. On failure, the boolean is false and the array is a list of
     * error messages.
     */
    public static function parseUpdateRequest(array $data, stdClass $rules = null): array
    {
        $errors = [];
        $result = [];
        $status = true;

        if ($data['code'] ?? false) {
            $format = $rules->account->codeFormat ?? '';
            if ($format !== '') {
                if (preg_match($format, $data['code'])) {
                    $result['code'] = $data['code'];
                } else {
                    $status = false;
                    $errors[] = "account code must match the form $format";
                }
            }
        }
        [$subStatus, $names] = LedgerNameController::parseRequestList(
            $data['names'] ?? [], true
        );
        if ($subStatus) {
            $result['names'] = $names;
        } else {
            $status = false;
            $errors = array_merge($errors, $names);
        }
        if (isset($data['parent'])) {
            $format = $rules->account->codeFormat ?? '';
            if (isset($data['parent'])) {
                [$subStatus, $parent] = self::parseParent($data['parent'], $format);
                if ($subStatus) {
                    $result['parent'] = $parent;
                } else {
                    $status = false;
                    $errors = array_merge($errors, $parent);
                }
            }
        }
        if (isset($data['category'])) {
            $result['category'] = $data['category'];
        }
        if (isset($data['closed'])) {
            $result['closed'] = $data['closed'];
        }

        if (($data['credit'] ?? false) && ($data['debit'] ?? false)) {
            $status = false;
            $errors[] = "account cannot be both debit and credit";
        } else {
            if (isset($data['credit'])) {
                $result['credit'] = $data['credit'];
            }
            if (isset($data['debit'])) {
                $result['debit'] = $data['debit'];
            }
        }

        return [$status, $status ? $result : $errors];
    }

    protected function success(Request $request)
    {
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->info(
                $request->getQueryString() . ' success',
                ['input' => $request->all()]
        );
    }

    /**
     * Update the specified resource in storage.
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
            $ledgerAccount = $this->fetchAccount($input);
            $ledgerAccount->checkRevision($input['revision'] ?? null);

            $rules = LedgerAccount::root()->flex->rules;
            /** @var array $parsed */
            [$status, $parsed] = self::parseUpdateRequest($input, $rules);
            if (!$status) {
                $this->badRequest($parsed);
            }
            DB::beginTransaction();
            $inTransaction = true;
            if (isset($parsed['code'])) {
                $ledgerAccount->code = $parsed['code'];
            }

            // Update the parent
            $ledgerParent = $this->updateParent($ledgerAccount, $parsed);

            // Apply a category flag
            if (isset($parsed['category'])) {
                if ($parsed['category']) {
                    // Verify that the parent is a category or root
                    if (!$ledgerParent->category) {
                        $this->errors[] = __(
                            "Account can't be a category because parent is not a category."
                        );
                        throw Breaker::fromCode(Breaker::INVALID_OPERATION);
                    }
                } elseif ($ledgerAccount->category) {
                    // Verify that no children are categories
                    $subCats = LedgerAccount::where('parentUuid', $ledgerAccount->ledgerUuid)
                        ->where('category', true)
                        ->count();
                    if ($subCats !== 0) {
                        $this->errors[] = __(
                            "Can't make account a non-category because at least"
                            . " one sub-account is a category."
                        );
                        throw Breaker::fromCode(Breaker::INVALID_OPERATION);
                    }
                }
            }

            $this->updateFlags($ledgerAccount, $parsed);
            $this->updateNames($ledgerAccount, $parsed);

            if (isset($parsed['extra'])) {
                $ledgerAccount->extra = $parsed['extra'];
            }
            $ledgerAccount->save();
            DB::commit();
            $inTransaction = false;
            $ledgerAccount->refresh();
            $this->success($request);
            $response['account'] = $ledgerAccount->toResponse();
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

    /**
     * @param LedgerAccount $ledgerAccount
     * @param array $parsed
     * @throws Breaker
     */
    protected function updateFlags(LedgerAccount $ledgerAccount, array $parsed)
    {
        // Set debit/credit, then check
        if (isset($parsed['credit'])) {
            $ledgerAccount->credit = $parsed['credit'];
            if (!isset($parsed['debit'])) {
                $ledgerAccount->debit = !$ledgerAccount->credit;
            }
        }
        if (isset($parsed['debit'])) {
            $ledgerAccount->debit = $parsed['debit'];
            if (!isset($parsed['credit'])) {
                $ledgerAccount->credit = !$ledgerAccount->debit;
            }
        }
        if ($ledgerAccount->credit && $ledgerAccount->debit) {
            $this->errors[] = __(
                "Account can not have both debit and credit flags set."
            );
            throw Breaker::fromCode(Breaker::INVALID_OPERATION);
        }
        if (!$ledgerAccount->credit && !$ledgerAccount->debit) {
            // Must ensure that no sub-accounts are category accounts
            $this->errors[] = __(
                "Debit and credit both cleared not yet implemented."
            );
            throw Breaker::fromCode(Breaker::NOT_IMPLEMENTED);
        }
        if (isset($parsed['closed'])) {
            // We need to check balances before closing the account
            // Is it possible to have sub-accounts still open?
            // Does closing a parent account just mean you can't post to it?
            // For lack of answers to these questions...
            $this->errors[] = __("Account closing not yet implemented.");
            throw Breaker::fromCode(Breaker::NOT_IMPLEMENTED);
        }
    }

    protected function updateNames(LedgerAccount $ledgerAccount, array $parsed)
    {
        foreach ($parsed['names'] as $name) {
            /** @var LedgerName $ledgerName */
            /** @noinspection PhpUndefinedMethodInspection */
            $ledgerName = $ledgerAccount->names->firstWhere('language', $name['language']);
            if ($ledgerName === null) {
                $ledgerName = new LedgerName();
                $ledgerName->ownerUuid = $ledgerAccount->ledgerUuid;
                $ledgerName->language = $name['language'];
            }
            $ledgerName->name = $name['name'];
            $ledgerName->save();
        }
    }

    /**
     * @param LedgerAccount $ledgerAccount
     * @param array $parsed
     * @return LedgerAccount
     * @throws Breaker
     * @throws Exception
     */
    protected function updateParent(LedgerAccount $ledgerAccount, array $parsed): LedgerAccount
    {
        if ($parsed['parent'] ?? false) {
            // Get the new parent record
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith($parsed['parent'])->first();
            if ($ledgerParent === null) {
                $this->errors[] = __("Specified parent not found.");
                throw Breaker::fromCode(Breaker::BAD_ACCOUNT);
            }
            $ledgerAccount->parentUuid = $ledgerParent->ledgerUuid;
        } else {
            // Get the existing parent
            $ledgerParent = LedgerAccount::find($ledgerAccount->parentUuid);
        }

        return $ledgerParent;
    }

}
