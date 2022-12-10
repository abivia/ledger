<?php

namespace Abivia\Ledger\Messages;

class CurrencyQuery extends Paginated
{

    /**
     * @var EntityRef For pagination, a reference to the last account in the previous page.
     */
    public string $after;

    protected static array $copyable = [
        'after',
        'limit'
    ];

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $query = new CurrencyQuery();
        $query->copy($data, $opFlags);

        return $query;
    }

}
