<?php

namespace Abivia\Ledger\Helpers;

use Abivia\Ledger\Models\LedgerAccount;
use Carbon\Carbon;
use Exception;

/**
 * Support for revision signatures on API calls.
 */
class Revision
{
    /**
     * Create a revision signature based on a hash of the ledger's salt and the
     * server-based last record update timestamp, with a fallback for database
     * managers that don't support server timestamps.
     *
     * @param Carbon|null $revision The database server maintained timestamp.
     * @param Carbon $fallback The Laravel maintained timestamp.
     *
     * @return string
     * @throws Exception
     */
    public static function create(?Carbon $revision, Carbon $fallback): string
    {
        $use = $revision ?? $fallback;
        return hash('ripemd256', LedgerAccount::root()->flex->salt . $use->toJSON());
    }


}
