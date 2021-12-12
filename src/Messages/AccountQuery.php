<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;
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
    public static function fromArray(array $data, int $opFlags): self
    {
        $query = new AccountQuery();
        $query->copy($data, $opFlags);
        if (isset($data['after'])) {
            $query->after = EntityRef::fromArray($data['after'], $opFlags);
        }

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function validate(int $opFlags): self
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
