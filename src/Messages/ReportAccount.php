<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;

class ReportAccount extends Message
{
    public string $balance;
    public bool $category;
    public string $code;
    public bool $credit;
    public string $creditTotal;
    public string $currency;
    protected static array $copyable = [
        'balance', 'category', 'code', 'credit', 'currency',
        'debit', 'depth', 'extra', 'flex', 'ledgerUuid',
        'name', 'parent'
    ];
    public bool $debit;
    public int $depth;
    public string $debitTotal;
    public string $extra;
    public $flex;
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
        $account->copy($data, $opFlags);

        return $account;
    }

    public function setReportTotals(int $decimals)
    {
        $this->creditTotal = '';
        $this->debitTotal = '';
        if ($this->debit) {
            $this->debitTotal = bcmul('-1', $this->total, $decimals);
        } elseif ($this->credit) {
            $this->creditTotal = $this->total;
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
