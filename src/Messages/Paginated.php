<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Models\LedgerAccount;

/**
 * Messages that return paginated results over a range.
 */
abstract class Paginated extends Message
{
    /**
     * @var int The maximum number of elements to return.
     */
    public int $limit;

    /**
     * @var string The key for the first resource.
     */
    public string $range;

    /**
     * @var string The key for the last resource.
     */
    public string $rangeEnding;

    /**
     * @inheritDoc
     */
    abstract public static function fromArray(array $data, int $opFlags = self::OP_ADD): self;

    /**
     * @inheritDoc
     */
    public function validate(int $opFlags = 0): self
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
