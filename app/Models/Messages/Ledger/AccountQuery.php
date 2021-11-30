<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;
use Carbon\Carbon;
use TypeError;

class AccountQuery extends Message {

    /**
     * @var EntityRef For pagination, first record after this position
     */
    public EntityRef $after;

    protected static array $copyable = [
        'limit'
    ];
    /**
     * @var ?EntityRef Ledger domain. If not provided the default is used.
     */
    public ?EntityRef $domain;

    public int $limit;
    public string $range;
    public string $rangeEnding;

    /**
     * @inheritDoc
     */
    public static function fromRequest(array $data, int $opFlags): Message
    {
        $query = new AccountQuery();
        $query->copy($data, $opFlags);
        if (isset($data['after'])) {
            $query->after = EntityRef::fromRequest($data['after'], $opFlags);
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
        return $this;
    }
}
