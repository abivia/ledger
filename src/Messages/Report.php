<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\ReportController;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerReport;
use Carbon\Carbon;

class Report extends Message
{
    protected static array $copyable = [
        'currency', 'name'
    ];
    public string $currency;
    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;
    public Carbon $fromDate;
    public string $name;
    public array $options = [];
    public Carbon $toDate;

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $report = new static();
        $report->copy($data, $opFlags);
        if (isset($data['domain'])) {
            $report->domain = EntityRef::fromMixed($data['domain']);
        }
        if (isset($data['options'])) {
            $report->options = $data['options'];
        }
        $dateFormat = (new LedgerReport)->getDateFormat();
        if (isset($data['fromDate'])) {
            $report->fromDate = Carbon::createFromFormat($dateFormat, $data['fromDate']);
        }
        if (isset($data['toDate'])) {
            $report->toDate = Carbon::createFromFormat($dateFormat, $data['toDate']);
        }

        return $report;
    }

    /**
     * Get the class for a report, or null if no class exists.
     * @param string $reportName
     * @return string|null
     */
    public static function getClass(string $reportName): ?string
    {
        $mapping = config('ledger.reports');

        if (!$mapping) {
            // No custom mapping provided, use a standard class name
            $reporter = 'Abivia\\Ledger\\Reports\\' . ucfirst($reportName) . 'Report';
        } elseif (isset($mapping[$reportName])) {
            $reporter = $mapping[$reportName];
        } else {
            $reporter = null;
        }
        return $reporter;
    }

    public function run(): array
    {
        $response = [];
        $controller = new ReportController();
        $report = $controller->generate($this);

        // Remove keys from the accounts so that they get sent as an array.
        $report['accounts'] = $report['accounts']->values();
        $response['report'] = $report;

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        if (!isset($this->name)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Report request name not found.')
            );
        }
        if (!static::getClass($this->name) === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Report :name not registered.', ['name' => $this->name])
            );
        }
        $rules = LedgerAccount::rules();
        if (!isset($this->domain)) {
            $this->domain = new EntityRef();
            $this->domain->code = $rules->domain->default;
        }
        $this->domain->validate(0);
        if (!isset($this->toDate)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Report to date (toDate) is required.')
            );
        }
        if (isset($this->options['language'])) {
            if (!is_array($this->options['language'])) {
                $this->options['language'] = [$this->options['language']];
            }
        } else {
            $this->options['language'] = [];
        }
        $fallback = $rules->language->default;
        if (!in_array($fallback, $this->options['language'])) {
            $this->options['language'][] = $fallback;
        }

        return $this;
    }
}
