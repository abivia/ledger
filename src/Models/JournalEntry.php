<?php
declare(strict_types=1);

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\CommonResponseProperties;
use Abivia\Ledger\Traits\HasRevisions;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\HigherOrderCollectionProxy;

/**
 * Records a transaction between accounts.
 *
 * @property array $arguments Translation arguments for the description.
 * @property bool $clearing Set when this is a clearing transaction.
 * @property Carbon $created_at Record creation timestamp.
 * @property string $createdBy Record creation entity.
 * @property string $currency The currency for this transaction.
 * @property string $domainUuid The UUID of the transaction's domain.
 * @property string $description Description of the transaction (untranslated).
 * @property Collection $entries The associated journal detail records.
 * @property string $extra Extra data for application use.
 * @property int $journalEntryId Primary key.
 * @property string $language The language this description is written in.
 * @property bool $locked Set when this transaction is not to be modified.
 * @property bool $opening Set if this is the opening balance entry.
 * @property string $journalReferenceUuid Optional reference to an associated entity.
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
    use CommonResponseProperties, HasFactory, HasRevisions;

    protected $attributes = [
        'opening' => false,
    ];

    protected $casts = [
        'arguments' => 'array',
        'clearing' => 'boolean',
        'locked' => 'boolean',
        'opening' => 'boolean',
        'reviewed' => 'boolean',
        'revision' => 'datetime',
        'transDate' => 'datetime',
    ];
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var string The date format for response messages.
     */
    static protected $dateFormatJson = 'Y-m-d\TH:i:s.u\Z';

    protected $fillable = [
        'arguments', 'clearing','createdBy', 'currency', 'description', 'domainUuid',
        'extra', 'journalReferenceUuid', 'language', 'locked', 'opening', 'reviewed',
        'transDate', 'updatedBy'
    ];
    protected $keyType = 'int';
    protected $primaryKey = 'journalEntryId';

    /**
     * @var string[] Relationships that should always be loaded
     */
    //protected $with = ['journal_detail'];

    //$by = Auth::id() ? 'User id ' . Auth::id() : 'unknown';

    /**
     * The revision Hash is computationally expensive, only calculated when required.
     *
     * @param $key
     * @return HigherOrderCollectionProxy|mixed|string|null
     * @throws Exception
     */
    public function __get($key)
    {
        if ($key === 'revisionHash') {
            return $this->getRevisionHash();
        }
        return parent::__get($key);
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearRevisionCache();
        });
    }

    /**
     * Check that the entry is unlocked and can be modified.
     *
     * @throws Breaker
     */
    public function checkUnlocked()
    {
        if ($this->locked) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('Journal entry :id is locked', ['id' => $this->journalEntryId])]
            );
        }
    }

    /**
     * Relationship to fetch the related JournalDetail records.
     *
     * @return HasMany
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'journalEntryId', 'journalEntryId');
    }

    /**
     * Store details from an Entry message into this JournalEntry.
     * @param Entry $message
     * @return $this
     */
    public function fillFromMessage(Entry $message): self
    {
        foreach ($this->fillable as $property) {
            if (isset($message->{$property})) {
                $this->{$property} = $message->{$property};
            }
        }
        if (isset($message->reference)) {
            $this->journalReferenceUuid = $message->reference->journalReferenceUuid;
        }
        if ($message->domain->uuid ?? false) {
            $this->domainUuid = $message->domain->uuid;
        }
        if ($message->journal->uuid ?? false) {
            $this->subJournalUuid = $message->journal->uuid;
        }

        return $this;
    }

    /**
     * Relationship to fetch any related JournalReferences.
     * @return HasManyThrough
     */
    public function references(): HasManyThrough
    {
        return $this->hasManyThrough(
            JournalReference::class,
            JournalDetail::class,
            'journalEntryId',
            'journalReferenceUuid',
            'journalEntryId',
            'journalReferenceUuid',
        );
    }

    /**
     * Generate an array suitable for a JSON response.
     *
     * @throws Exception
     */
    public function toResponse(int $opFlags): array
    {
        $response = [];
        $response['id'] = $this->journalEntryId;
        $response['date'] = $this->transDate->format(static::$dateFormatJson);
        $except = ['names'];
        if ($opFlags & Message::OP_GET) {
            if (isset($this->subJournalUuid)) {
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                $subJournal = SubJournal::find($this->subJournalUuid);
                $response['journal'] = $subJournal->code;
                $response['journalUuid'] = $this->subJournalUuid;
            }
            $response['description'] = $this->description;
            if (count($this->arguments)) {
                $response['descriptionArgs'] = $this->arguments;
            }
            $response['language'] = $this->language;
            $response['opening'] = $this->opening;
            if ($this->journalReferenceUuid !== null) {
                $response['reference'] = $this->journalReferenceUuid;
            }
            $response['clearing'] = $this->clearing;
            $response['reviewed'] = $this->reviewed;
            $response['locked'] = $this->locked;
            $response['currency'] = $this->currency;
            $response['details'] = [];
            /** @var JournalDetail $detail */
            /** @noinspection PhpUndefinedFieldInspection */
            foreach ($this->details as $detail) {
                $response['details'][] = $detail->toResponse();
            }
        } else {
            $except[] = 'extra';
        }

        return $this->commonResponses($response, $except);
    }

}
