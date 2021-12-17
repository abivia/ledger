<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerReport;
use Carbon\Carbon;

class Report extends Message
{
    protected static array $copyable = [
        'currency', 'name'
    ];
    public string $currency;
    public Carbon $fromDate;
    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;

    public string $name;
    public array $options = [];
    public Carbon $toDate;

    /**
     * @var array Option defaults for each report
     */
    public static array $reportNames = [
        'trialBalance' => ['depth' => 0]
    ];
    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = 0): self
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
     * @inheritDoc
     */
    public function validate(int $opFlags = 0): self
    {
        if (!isset($this->name)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST, __('Report request name not found.')
            );
        }
        if (!isset(self::$reportNames[$this->name])) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Report :name not found.', ['name' => $this->name])
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
        $this->options['language'][] = LedgerAccount::rules()->language->default;

        return $this;
    }
}
