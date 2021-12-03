<?php

namespace Abivia\Ledger\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class EntryQuery extends Message {

    /**
     * @var int For pagination, ID of first record after this position
     */
    public int $after;
    /**
     * @var Carbon For pagination, date of first record after this position
     */
    public Carbon $afterDate;

    public string $amount;
    public string $amountMax;

    protected static array $copyable = [
        'after', 'limit'
    ];
    public string $currency;
    public Carbon $date;
    public Carbon $dateEnding;
    /**
     * @var EntityRef[]
     */
    public array $entities = [];
    /**
     * @var ?EntityRef Ledger domain. If not provided the default is used.
     */
    public ?EntityRef $domain;
    /**
     * @var Message[] Message objects that limit query results
     */
    public array $filters = [];
    public int $limit;
    public Reference $reference;

    /**
     * @inheritDoc
     */
    public static function fromRequest(array $data, int $opFlags): Message
    {
        $query = new self();
        $query->copy($data, $opFlags);
        foreach (['afterDate', 'date', 'dateEnding'] as $dateProperty) {
            if (isset($data[$dateProperty])) {
                $query->$dateProperty = new Carbon($data[$dateProperty]);
            }
        }
        if (isset($data['amount'])) {
            if (is_array($data['amount'])) {
                $query->amount = $data['amount'][0];
                if (isset($data['amount'][1])) {
                    $query->amountMax = $data['amount'][1];
                }
            } else {
                $query->amount = $data['amount'];
            }
        }
        if (isset($data['reference'])) {
            $query->reference = new Reference();
            $query->reference->code = $data['reference'];
        }

        return $query;
    }

    public function query(): Builder
    {
        /** @var LedgerDomain $ledgerDomain */
        $ledgerDomain = LedgerDomain::findWith($this->domain)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__('Domain not found.')]
            );
        }
        $this->domain->uuid = $ledgerDomain->domainUuid;
        if (!isset($this->currency)) {
            $this->currency = $ledgerDomain->currencyDefault;
        }

        $query = JournalEntry::query();
        $this->queryAmount($query);
        $this->queryDate($query);
        $this->queryDomain($query);
        $this->queryPagination($query);
        $this->queryReference($query);

        return $query;
    }

    private function queryAmount(Builder $query)
    {
        $ledgerCurrency = LedgerCurrency::find($this->currency);
        if ($ledgerCurrency === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__('Currency :code not found.', ['code' => $this->currency])]
            );
        }
        $query->where('currency', $this->currency);
        if (!isset($this->amountMax) && !isset($this->amount)) {
            return;
        }
        // Normalize and validate the numbers
        $decimals = $ledgerCurrency->decimals;
        if (isset($this->amount)) {
            $this->amount = bcmul($this->amount, '1', $decimals);
            if (bccomp($this->amount, '0', $decimals) === -1) {
                $this->amount = bcmul($this->amount, '-1', $decimals);
            }
        }
        if (isset($this->amountMax)) {
            $this->amountMax = bcmul($this->amountMax, '1', $decimals);
            if (bccomp($this->amountMax, '0', $decimals) === -1) {
                $this->amountMax = bcmul($this->amountMax, '-1', $decimals);
            }
        }
        if (isset($this->amount) && isset($this->amountMax)) {
            if (bccomp($this->amount, $this->amountMax, $decimals) === 1) {
                $swap = $this->amount;
                $this->amount = $this->amountMax;
                $this->amountMax = $swap;
            }
            $cast = 'decimal(' . LedgerCurrency::AMOUNT_SIZE . ",$decimals)";
            $query->whereHas(
                'details',
                function (Builder $query) use ($cast) {
                    // whereRaw(cast to decimal using currency data)
                    $query->whereRaw(
                        "abs(cast(`amount` AS $cast)) between $this->amount and $this->amountMax"
                    );
                }
            );
        } else {
            $query->whereRelation('details', 'amount', $this->amount);
        }
    }

    private function queryDate(Builder $query)
    {
        // Apply the date range
        $dateFormat = LedgerAccount::systemDateFormat();
        if (isset($this->date) && isset($this->dateEnding)) {
            $query->whereBetween(
                'transDate',
                [$this->date->format($dateFormat), $this->dateEnding->format($dateFormat)]
            );
        } elseif (isset($this->date)) {
            $query->where('transDate', '>=', $this->date->format($dateFormat));
        } elseif (isset($this->dateEnding)) {
            $query->where('transDate', '<=', $this->dateEnding->format($dateFormat));
        }
    }

    private function queryDomain(Builder $query)
    {
        // Apply the domain
        $query->where('domainUuid', '=', $this->domain->uuid);
    }

    private function queryPagination(Builder $query)
    {
        // Add pagination
        if (isset($this->after)) {
            $afterDate = $this->afterDate->format(LedgerAccount::systemDateFormat());
            $query->where('transDate', '>', $afterDate)
                ->orWhere(function ($query) use ($afterDate){
                    $query->where('journalEntryId','>', $this->after)
                        ->where('transDate', '=', $afterDate);
                });
        }
        if (isset($this->limit)) {
            $query->limit($this->limit);
        }
    }

    private function queryReference($query)
    {
        // If there's a reference qualify the results with it
        if (isset($this->reference)) {
            $query->whereRelation(
                'references',
                'code',
                $this->reference->code
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function validate(int $opFlags): Message
    {
        // Limit results on API calls
        if ($opFlags & self::F_API) {
            $limit = LedgerAccount::rules()->pageSize;
            if (isset($this->limit)) {
                $this->limit = min($this->limit, $limit);
            } else {
                $this->limit = $limit;
            }
        }

        // Make sure a page start makes sense.
        if (isset($this->after) !== isset($this->afterDate)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Both after and afterDate are required.')
            );
        }

        if (!isset($this->domain)) {
            $this->domain = new EntityRef();
            $this->domain->code = LedgerAccount::rules()->domain->default;
        }
        // Validate the reference
        if (isset($this->reference)) {
            $this->reference->validate($opFlags);
        }
        return $this;
    }
}
