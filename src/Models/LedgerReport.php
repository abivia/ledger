<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Messages\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Support for cached reports.
 *
 * @method static LedgerReport create(array $attributes) Provided by model.
 * @property string $currency The report's currency.
 * @property string $domainUuid the ledger domain for this report.
 * @property ?Carbon $fromDate The period start this report was generated for.
 * @property int $id Primary key
 * @property int $journalEntryId The last journal entry at the time of report creation.
 * @property string $name The report name
 * @property string $reportData
 * @property Carbon $toDate The period end this report was generated for.
 * @mixin Builder
 */
class LedgerReport extends Model
{
    use HasFactory;

    protected $casts = [
        'fromDate' => 'datetime',
        'toDate' => 'datetime',
    ];
    protected $dateFormat = 'Y-m-d';
    protected $fillable = [
        'currency', 'domainUuid', 'fromDate', 'journalEntryId',
        'name', 'reportData', 'toDate'
    ];

    /**
     * @var bool Disable timestamps.
     */
    public $timestamps = false;

    public static function createFromMessage(Report $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        $instance->save();
        $instance->refresh();

        return $instance;
    }

    public function toResponse(): array
    {
        return [
            'name' => $this->name,
        ];
    }

}
