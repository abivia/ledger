<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
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

    /**
     * @var string Minimum transaction amount.
     */
    public string $amount;

    /**
     * @var string Maximum transaction amount.
     */
    public string $amountMax;

    protected static array $copyable = [
        'after', 'description', 'limit',
    ];

    /**
     * @var string The transaction currency.
     */
    public string $currency;

    /**
     * @var Carbon The minimum transaction date.
     */
    public Carbon $date;

    /**
     * @var Carbon The maximum transaction date.
     */
    public Carbon $dateEnding;

    /**
     * @var string Transaction description to match to.
     */
    public string $description;

    /**
     * @var EntityRef The ledger domain to query.
     */
    public EntityRef $domain;

    /**
     * @var EntityRef[] [future use]
     */
    public array $entities = [];

    /**
     * @var Message[] Message objects that limit query results
     */
    public array $filters = [];

    /**
     * @var int The maximum number of entries to return.
     */
    public int $limit;

    /**
     * @var Reference A link to an external entity.
     */
    public Reference $reference;

    /**
     * @var bool|null Find reviewed/unreviewed/all states.
     */
    public ?bool $reviewed;

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
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
        if (isset($data['domain'])) {
            $query->domain = EntityRef::fromMixed($data['domain']);
        }
        if (isset($data['reference'])) {
            $query->reference = new Reference();
            $query->reference->code = $data['reference'];
        }

        return $query;
    }

    /**
     * Generate a query to retrieve the requested entries.
     *
     * @return Builder
     * @throws Breaker
     */
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
        if (isset($this->reviewed)) {
            $query = $query->where('reviewed', '=', $this->reviewed);
        }
        $this->queryAmount($query);
        $this->queryDate($query);
        $this->queryDescription($query);
        $this->queryDomain($query);
        $this->queryPagination($query);
        $this->queryReference($query);

        return $query;
    }

    /**
     * Add the amount criteria to the query.
     *
     * @param Builder $query
     * @return void
     * @throws Breaker
     */
    private function queryAmount(Builder $query): void
    {
        $ledgerCurrency = LedgerCurrency::findOrBreaker($this->currency);
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

    /**
     * Add the date criteria to the query.
     *
     * @param Builder $query
     * @return void
     */
    private function queryDate(Builder $query): void
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

    /**
     * Add the domain criteria to the query.
     *
     * @param Builder $query
     * @return void
     */
    private function queryDescription(Builder $query): void
    {
        if (isset($this->description)) {
            // Apply the description
            $query->where('description', 'like', $this->description);
        }
    }

    /**
     * Add the domain criteria to the query.
     *
     * @param Builder $query
     * @return void
     */
    private function queryDomain(Builder $query): void
    {
        // Apply the domain
        $query->where('domainUuid', '=', $this->domain->uuid);
    }

    /**
     * Add the pagination criteria to the query.
     *
     * @param Builder $query
     * @return void
     * @throws Breaker
     */
    private function queryPagination(Builder $query): void
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

    /**
     * Add the external reference criteria to the query.
     *
     * @param Builder $query
     * @return void
     */
    private function queryReference(Builder $query): void
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
     * @throws Breaker
     */
    public function run(): array {
        $controller = new JournalEntryController();
        $entries = [];
        /** @var JournalEntry $entry */
        foreach ($controller->query($this, $this->opFlags) as $entry) {
            $entries[] = $entry->toResponse(Message::OP_GET);
        }

        return ['entries' => $entries];
    }

    /**
     * @inheritDoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
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
