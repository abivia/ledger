<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

class SubJournalQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last sub-journal in the previous page.
     */
    public string $after;

}
