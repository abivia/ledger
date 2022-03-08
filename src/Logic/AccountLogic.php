<?php
declare(strict_types=1);

namespace Abivia\Ledger\Logic;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerName;
use Exception;
use Illuminate\Support\Facades\DB;

class AccountLogic
{
    private string $accountTable;
    private string $balanceTable;
    private string $nameTable;
    /**
     * @var string[]
     */
    private array $relatedAccounts;

    public function __construct()
    {
        $this->accountTable = (new LedgerAccount())->getTable();
        $this->balanceTable = (new LedgerBalance())->getTable();
        $this->nameTable = (new LedgerName())->getTable();
    }

    public function canBeDeleted(LedgerAccount $ledgerAccount): bool
    {
        // look for sub-accounts with associated transactions
        $this->relatedAccounts = $ledgerAccount->getSubAccountList();
        $subCats = DB::table($this->accountTable)
            ->join($this->balanceTable,
                $this->accountTable . '.ledgerUuid', '=',
                $this->balanceTable . '.ledgerUuid'
            )
            ->whereIn($this->accountTable . '.ledgerUuid', $this->relatedAccounts)
            ->count();

        return $subCats === 0;
    }

    /**
     * @throws Exception
     */
    public function delete(LedgerAccount $ledgerAccount): bool
    {
        if (!$this->canBeDeleted($ledgerAccount)) {
            return false;
        }
        try {
            DB::beginTransaction();
            $inTransaction = true;
            Db::table($this->balanceTable)
                ->whereIn('ledgerUuid', $this->relatedAccounts)
                ->delete();
            Db::table($this->nameTable)
                ->whereIn('ownerUuid', $this->relatedAccounts)
                ->delete();
            Db::table($this->accountTable)
                ->whereIn('ledgerUuid', $this->relatedAccounts)
                ->delete();
            DB::commit();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return true;
    }

}
