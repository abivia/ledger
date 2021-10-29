<?php
declare(strict_types=1);

namespace App\Models;

use App\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use DateTime;
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
 * @property DateTime $created_at Record creation timestamp.
 * @property string $createdBy Record creation entity.
 * @property string $currency The currency for this transaction.
 * @property string $description Description of the transaction (untranslated).
 * @property Collection $entries The associated journal detail records.
 * @property string $extra Extra data for application use.
 * @property string $journalEntryId UUID primary key.
 * @property string $language The language this description is written in.
 * @property bool $posted Set when the transaction has been posted to the ledgers.
 * @property DateTime $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid UUID of the sub-journal (if any)
 * @property DateTime $transDate The date/time of the transaction.
 * @property int $txn_record_id The primary key in the txn_record table.
 * @property DateTime $updated_at Last record update timestamp.
 * @property string $updatedBy Record update entity.
 */
class JournalEntry extends Model
{
    use HasFactory, UuidPrimaryKey;

    protected $casts = [
        'arguments' => 'array',
        'posted' => 'boolean',
    ];
    protected $dates = [
        'trans_date',
    ];
    public int $exponent = -1;
    private bool $posting = false;
    protected $primaryKey = 'journalEntryId';
    protected $with = ['journal_detail'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->currency = 'XXX';
        $this->description = null;
        $this->posted = 0;
        $this->transDate = new Carbon();
        $by = Auth::id() ? 'User id ' . Auth::id() : 'unknown';
        $this->created_by = $by;
        $this->updated_by = $by;
    }

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

    public function check()
    {
        if (!$this->transDate instanceof Carbon) {
            return 'Date is invalid or not set.';
        }
        if ($this->description === null || $this->description === '') {
            return 'Description is required.';
        }
        if (!$this->findCurrency($this->currency)) {
            return "Unsupported currency: '{$this->currency}'.";
        }
        if ($this->entries->count() === 0) {
            return 'Transaction has no entries.';
        }
        $balance = '0';
        $debitCount = 0;
        $creditCount = 0;
        foreach ($this->entries as $item) {
            $side = bccomp($item->amount, '0', $this->exponent);
            if ($side === 0) {
                return 'Invalid zero-valued entry.';
            }
            if ($side < 0) {
                ++$debitCount;
            } else {
                ++$creditCount;
            }
            $balance = bcadd($balance, $item->amount, $this->exponent);
        }
        if ($debitCount > 1 && $creditCount > 1) {
            return "Invalid split: {$debitCount} debits, {$creditCount} credits.";
        }
        if (bccomp($balance, '0', $this->exponent) !== 0) {
            return "Transaction is unbalanced by {$balance}.";
        }

        return true;
    }

    public function credit($entity, $code, $amount)
    {
        $this->add(JournalDetail::make()->detail(
            $entity, $code, bcmul('-1', $amount, $this->exponent)
        ));

        return $this;
    }

    public function currency(string $code): self
    {
        $currency = $this->findCurrency($code);
        if (!$currency) {
            throw new RuntimeException("Unsupported currency: $code");
        }
        $this->currency = $currency['code'];
        $this->exponent = $currency['exponent'];

        return $this;
    }

    public function debit($entity, $code, $amount)
    {
        $this->add(JournalDetail::make()->detail($entity, $code, $amount));

        return $this;
    }

    public function deepWasChanged(): bool
    {
        if ($this->wasChanged()) {
            return true;
        }
        foreach ($this->entries as $item) {
            if ($item->wasChanged()) {
                return true;
            }
        }
        return false;
    }

    public function description($desc, array $args = []): self
    {
        if ($desc instanceof LocalString) {
            $this->description = $desc->text;
            $this->arguments = $desc->replacements;
        } else {
            $this->description = $desc;
            $this->arguments = $args;
        }

        return $this;
    }

    /**
     * Modify an existing entry.
     *
     * @param JournalDetail $entry
     * @return JournalEntry|null
     */
    public function edit(JournalDetail $entry): ?self
    {
        foreach ($this->entries as $key => $search) {
            if ($search->journalDetailId === $entry->journalDetailId) {
                $this->entries[$key] = $entry;
                return $this;
            }
        }

        return null;
    }

    public function entries()
    {
        return $this->hasMany(JournalDetail::class, 'journalDetailId', 'journalDetailId');
    }

    protected function findCurrency($code)
    {
        return false;
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
