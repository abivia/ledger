<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\ReportData;

abstract class AbstractReport
{
    public abstract function collect(Report $message);

    public abstract function prepare(ReportData $reportData);
}
