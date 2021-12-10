<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Messages\Report;

abstract class AbstractReport
{
    public abstract function collect(Report $message);

    public abstract function prepare(Report $message, $reportData);
}
