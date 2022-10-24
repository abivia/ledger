<?php

namespace Abivia\Ledger\Models;


/**
 * A Ledger account formatted for reporting purposes.
 */
class ReportAccount extends NoDatabase
{
    /**
     * @var string Balance of this account, not including subaccount balances.
     */
    public string $balance;
    /**
     * @var bool Set if this is a category account.
     */
    public bool $category;
    /**
     * @var string The account's ledger code
     */
    public string $code;
    /**
     * @var bool Set if this is a credit account.
     */
    public bool $credit;
    /**
     * @var string An unsigned balance if the account has a credit, otherwise blank.
     */
    public string $creditBalance;
    /**
     * @var string The currency that account toals/balances represent.
     */
    public string $currency;

    /**
     * @var array|string[] Copyable properties.
     */
    protected static array $copyable = [
        'balance', 'category', 'code', 'credit', 'currency',
        'debit', 'depth', 'extra', 'flex', 'ledgerUuid',
        'name', 'parent'
    ];
    /**
     * @var bool Set if this is a debit account.
     */
    public bool $debit;
    /**
     * @var int The nesting level of this account in the Chart of Accounts.
     */
    public int $depth;
    /**
     * @var string An unsigned balance if the account has a debit, otherwise blank.
     */
    public string $debitBalance;
    /**
     * @var string Application dependent extra information for the account.
     */
    public string $extra;
    /**
     * @var mixed
     */
    protected $flex;
    /**
     * @var string The account's unique ID
     */
    public string $ledgerUuid;
    /**
     * @var string Language-resolved name of the account
     */
    public string $name;
    /**
     * @var string Account code of the parent account (if any).
     */
    public string $parent;
    /**
     * @var string This is the total of the account balance plus all subaccount balances.
     */
    public string $total;

    private function format(
        string $amount,
        string $decimal,
        string $negative,
        string $thousands
    ): string
    {
        [$whole, $fractional] = explode('.', $amount);
        $signPre = '';
        $signPost = '';
        if (str_starts_with($whole, '-')) {
            if ($negative === '(') {
                $signPre = '(';
                $signPost = ')';
            } else {
                $signPre = '-';
            }
            $whole = substr($whole, 1);
        }
        $groups = str_split(strrev($whole), 3);
        $whole = strrev(implode($thousands, $groups));
        return "$signPre$whole$decimal$fractional$signPost";
    }

    /**
     * Build a new object from a data array.
     *
     * @param array $data The source data.
     * @return ReportAccount A new object populated with the data.
     */
    public static function fromArray(array $data): self
    {
        $account = new static();
        $account->copy($data);

        return $account;
    }

    public function setReportTotals(
        int $decimals,
        string $decimal = '.',
        string $negative = '-',
        string $thousands = ''
    )
    {
        if ($this->debit) {
            $this->debitBalance = $this->format(
                bcmul('-1', $this->balance, $decimals),
                $decimal, $negative, $thousands
            );
            $this->creditBalance = '';
        } else {
            $this->debitBalance = '';
            $this->creditBalance = $this->format(
                $this->balance, $decimal, $negative, $thousands
            );
        }
    }

    /**
     * @deprecated Should be unused.
     */
    public function validate(int $opFlags): self
    {
        return $this;
    }
}
