<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;
use Carbon\Carbon;
use TypeError;

class EntryQuery extends Message {

    /**
     * @var int For pagination, ID of first record after this position
     */
    public int $after;
    /**
     * @var Carbon For pagination, date of first record after this position
     */
    public Carbon $afterDate;

    protected static array $copyable = [
        'after', 'limit'
    ];
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

        return $query;
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
        if (isset($this->after) !== isset($this->afterDate)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Both after and afterDate are required.')
            );
        }
        return $this;
    }
}
