<?php
declare(strict_types=1);

namespace App\Http\Controllers\LedgerAccount;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerAccountController;
use App\Models\LedgerAccount;
use App\Models\LedgerName;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AddController extends LedgerAccountController
{

    /**
     * Adding an account to the ledger.
     *
     * @param Request $request
     * @return array
     */
    //#[ArrayShape(['time' => "\Illuminate\Support\Carbon", 'account' => "array", 'errors' => "string[]"])]
    public function run(Request $request): array
    {
        $inTransaction = false;
        $response = [];
        $this->errors = [];
        try {
            $parsed = $this->validateRequest($request);
            // check for duplicate
            if (LedgerAccount::where('code', $parsed['code'])->first() !== null) {
                $this->errors[] = __(
                    "Account :code already exists.",
                    ['code' => $parsed['code']]
                );
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }

            DB::beginTransaction();
            $inTransaction = true;
            $ledgerAccount = $this->createAccount($parsed);
            DB::commit();
            $inTransaction = false;
            $this->success($request);
            $response['account'] = $ledgerAccount->toResponse();
        } catch (Breaker $exception) {
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }
        if ($inTransaction) {
            DB::rollBack();
        }
        $response['time'] = new Carbon();

        return $response;
    }

    private function createAccount(array $input): LedgerAccount
    {
        $ledgerAccount = new LedgerAccount();
        $ledgerAccount->code = $input['code'];
        $ledgerAccount->parentUuid = $input['parent']['uuid'];
        $ledgerAccount->debit = $input['debit'];
        $ledgerAccount->credit = $input['credit'];
        $ledgerAccount->category = $input['category'];
        $ledgerAccount->extra = $input['extra'] ?? null;
        $ledgerAccount->save();
        $ledgerAccount->refresh();
        // Create the name records
        foreach ($input['names'] as $name) {
            $name['ownerUuid'] = $ledgerAccount->ledgerUuid;
            LedgerName::create($name);
        }

        return $ledgerAccount;
    }

    /**
     * @param Request $request
     * @return array
     * @throws Breaker
     * @throws Exception
     */
    private function validateRequest(Request $request): array
    {
        $rules = LedgerAccount::root()->flex->rules;
        [$status, $parsed] = self::parseCreateRequest($request->all(), $rules);
        if (!$status) {
            $this->errors = array_merge($this->errors, $parsed);
            throw Breaker::fromCode(Breaker::BAD_REQUEST);
        }

        // No parent implies the ledger root
        if (!isset($parsed['parent'])) {
            $ledgerParent = LedgerAccount::root();
        } else {
            // Fetch the parent
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith($parsed['parent'])->first();
            if ($ledgerParent === null) {
                $this->errors[] = __("Specified parent not found.");
                throw Breaker::fromCode(Breaker::BAD_ACCOUNT);
            }
        }
        $parsed['parent']['uuid'] = $ledgerParent->ledgerUuid;

        // Validate and inherit flags
        if ($parsed['category'] && !$ledgerParent->category) {
            $this->errors[] = __(
                "Can't create a category under a parent that is not a category."
            );
            throw Breaker::fromCode(Breaker::INVALID_OPERATION);
        }
        if (!($parsed['credit'] || $parsed['debit'])) {
            if (!($ledgerParent->credit || $ledgerParent->debit)) {
                $this->errors[] = __(
                    "Unable to inherit debit/credit status from parent."
                );
                throw Breaker::fromCode(Breaker::INVALID_OPERATION);
            }
            $parsed['credit'] = $ledgerParent->credit;
            $parsed['debit'] = $ledgerParent->debit;
        }

        return $parsed;
    }

}
