<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

class CurrencyQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last currency in the previous page.
     */
    public string $after;

}
