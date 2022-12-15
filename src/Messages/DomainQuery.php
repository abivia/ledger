<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

class DomainQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last domain in the previous page.
     */
    public string $after;

}
