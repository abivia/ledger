<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\Messages\Ledger\Entry;
use App\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use function bcadd;
use function bccomp;

/**
 * Records a transaction between accounts.
 *
 * @property array $arguments Translation arguments for the description.
 * @property Carbon $created_at Record creation timestamp.
 * @property string $createdBy Record creation entity.
 * @property string $currency The currency for this transaction.
 * @property string $domainUuid The UUID of the transaction's domain.
 * @property string $description Description of the transaction (untranslated).
 * @property Collection $entries The associated journal detail records.
 * @property string $extra Extra data for application use.
 * @property string $journalEntryId UUID primary key.
 * @property string $language The language this description is written in.
 * @property bool $posted Set when the transaction has been posted to the ledgers.
 * @property bool $reviewed Set when the transaction has been reviewed.
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid UUID of the sub-journal (if any)
 * @property Carbon $transDate The date/time of the transaction.
 * @property Carbon $updated_at Last record update timestamp.
 * @property string $updatedBy Record update entity.
 * @mixin Builder
 */
class JournalEntry extends Model
{
    use HasFactory, UuidPrimaryKey;

    protected $casts = [
        'arguments' => 'array',
        'posted' => 'boolean',
        'reviewed' => 'boolean',
        'transDate' => 'datetime',
    ];
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'arguments', 'createdBy', 'currency', 'description', 'extra',
        'language', 'posted', 'reviewed', 'transDate', 'updatedBy'
    ];
    private bool $posting = false;
    protected $primaryKey = 'journalEntryId';

    /**
     * @var string[] Relationships that should always be loaded
     */
    protected $with = ['journal_detail'];

    //$by = Auth::id() ? 'User id ' . Auth::id() : 'unknown';

    public function add(JournalDetail $entry): self
    {
        /** @var JournalDetail $item */
        foreach ($this->entries as $item) {
            if ($item->sameAccount($entry)) {
                throw new RuntimeException(
                    "Can't add: duplicate account reference."
                );
            }
        }
        $entry->amount = bcadd($entry->amount, '0', $this->exponent);
        $this->entries->push($entry);

        return $this;
    }

    /**
     * Set up event hooks
     */
    protected static function booted()
    {
        static::retrieved(function ($record) {
            // Validate currency and load exponent
            $record->currency($record->currency);
        });
        static::saved(function ($record) {
            foreach ($record->entries as $item) {
                $item->journalDetailId = $record->journalDetailId;
                if ($item->isDirty()) {
                    $item->save();
                }
            }
            if (!$record->posting) {
                DB::commit();
            }
        });
        static::saving(function ($record) {
            $state = $record->check();
            if ($state !== true) {
                throw new RuntimeException('Unable to save: ' . $state);
            }
            if (
                $record->posted
                && !$record->posting
                && $record->deepWasChanged()
            ) {
                throw new RuntimeException('Unable to save: already posted.');
            }
            if (!$record->posting) {
                DB::beginTransaction();
            }
            $record->updated_by = Auth::id() ? 'User id ' . Auth::id() : 'unknown';
        });

    }

    public function description($desc, array $args = []): self
    {
        $this->description = $desc;
        $this->arguments = $args;

        return $this;
    }

    public function entries()
    {
        return $this->hasMany(JournalDetail::class, 'journalDetailId', 'journalDetailId');
    }

    public function fillFromMessage(Entry $message): self
    {
        foreach ($this->fillable as $property) {
            if (isset($message->{$property})) {
                $this->{$property} = $message->{$property};
            }
        }
        if ($message->domain->uuid ?? false) {
            $this->domainUuid = $message->domain->uuid;
        }
        if ($message->journal->uuid ?? false) {
            $this->subJournalUuid = $message->journal->uuid;
        }

        return $this;
    }

    public function getBalance()
    {
        $balance = '0';
        foreach ($this->entries as $item) {
            $balance = bcadd($balance, $item->amount, $this->exponent);
        }

        return $balance;
    }

    public function getDescription()
    {
        return __($this->description, $this->arguments, $this->language);
    }

    public function onDate($date = null)
    {
        if (!$date instanceof Carbon) {
            $date = new Carbon($date);
        }
        $this->trans_date = $date;

        return $this;
    }

    public function post()
    {
        if (!$this->posted) {
            try {
                $this->posting = true;
                DB::beginTransaction();
                $this->posted = true;
                $this->save();
                // Update account balances. Thanks to bcmath we have to fetch/save.
                foreach ($this->entries as $item) {
                    $account = LedgerAccount::firstOrNew(LedgerAccount::pk([
                        $item->entity_uuid, $item->code, $this->currency
                    ]));
                    $account->balance = bcadd(
                        $account->balance, $item->amount, $this->exponent
                    );
                    $account->save();
                }
                DB::commit();
                $this->posting = false;
            } catch (\Exception $err) {
                DB::rollBack();
                $this->posting = false;
                throw $err;
            }
        }
        return $this;
    }

    public function unpost()
    {
        if ($this->posted) {
            try {
                $this->posting = true;
                DB::beginTransaction();
                // Reverse account balances. Thanks to bcmath we have to fetch/save.
                foreach ($this->entries as $item) {
                    $account = LedgerAccount::firstOrNew(LedgerAccount::pk([
                        $item->entity_uuid, $item->code, $this->currency
                    ]));
                    $account->balance = bcsub(
                        $account->balance, $item->amount, $this->exponent
                    );
                    $account->save();
                }
                $this->posted = false;
                $this->save();
                DB::commit();
                $this->posting = false;
            } catch (\Exception $err) {
                DB::rollBack();
                $this->posting = false;
                throw $err;
            }
        }
        return $this;
    }

}
