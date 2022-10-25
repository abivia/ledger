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
     * @var EntityRef For pagination, a reference to the last account in the previous page.
     */
    public EntityRef $after;

    protected static array $copyable = [
        'limit'
    ];

    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;

    /**
     * @var int The maximum number of accounts to return.
     */
    public int $limit;

    /**
     * @var string The code for the first account.
     */
    public string $range;

    /**
     * @var string The code for the last account.
     */
    public string $rangeEnding;

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $query = new AccountQuery();
        $query->copy($data, $opFlags);
        if (isset($data['after'])) {
            $query->after = EntityRef::fromArray($data['after'], $opFlags);
        }
        if (isset($data['domain'])) {
            $query->domain = EntityRef::fromMixed($data['domain']);
        }

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function validate(int $opFlags = 0): self
    {
        // Limit results on API calls
        if ($opFlags & self::F_API) {
            if (!isset($this->limit)) {
                $this->limit = LedgerAccount::rules()->pageSize;
            }
        }
        return $this;
    }
}
