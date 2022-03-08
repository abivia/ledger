<?php

namespace Abivia\Ledger\Models;


/**
 * A Ledger account formatted for reporting purposes.
 */
class ReportAccount extends NoDatabase
{
    public string $balance;
    public bool $category;
    public string $code;
    public bool $credit;
    public string $creditBalance;
    public string $currency;
    protected static array $copyable = [
        'balance', 'category', 'code', 'credit', 'currency',
        'debit', 'depth', 'extra', 'flex', 'ledgerUuid',
        'name', 'parent'
    ];
    public bool $debit;
    public int $depth;
    public string $debitBalance;
    public string $extra;
    /**
     * @var mixed
     */
    protected $flex;
    public string $ledgerUuid;
    public string $name;
    public string $parent;
    public string $total;

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags): self
    {
        $account = new static();
        $account->copy($data);

        return $account;
    }

    public function setReportTotals(int $decimals)
    {
        if ($this->debit) {
            $this->debitBalance = bcmul('-1', $this->balance, $decimals);
            $this->creditBalance = '';
        } else {
            $this->debitBalance = '';
            $this->creditBalance = $this->balance;
        }
    }

    /**
     * @inheritDoc
     */
    public function validate(int $opFlags): self
    {
        return $this;
    }
}
