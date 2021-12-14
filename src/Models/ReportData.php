<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Messages\Report;
use Carbon\Carbon;

class ReportData extends NoDatabase
{
    /**
     * @var ReportAccount[]
     */
    public array $accounts;

    /**
     * @var int The number of decimal places in the requested currency
     */
    public int $decimals;

    /**
     * @var int The last JournalEntry ID when this report was created.
     */
    public int $journalEntryId;

    /**
     * @var Report The Report message used to generate this report.
     */
    public Report $request;

}
