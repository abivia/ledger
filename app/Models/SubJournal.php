<?php

namespace App\Models;

use App\Helpers\Revision;
use App\Models\Messages\Ledger\SubJournal as JournalMessage;
use App\Traits\HasRevisions;
use App\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domains assigned within the ledger.
 *
 * @method static SubJournal create(array $attributes) Provided by model.
 * @property string $code Unique identifier for the sub-journal.
 * @property Carbon $created_at When the record was created.
 * @property string $extra Application defined information.
 * @property LedgerName[] $names
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid Identifier for this journal.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class SubJournal extends Model
{
    use HasFactory, HasRevisions, UuidPrimaryKey;

    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['code', 'extra'];
    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'subJournalUuid';

    public static function createFromMessage(JournalMessage $message): self
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

    public function names(): HasMany
    {
        return $this->hasMany(LedgerName::class, 'ownerUuid', 'domainUuid');
    }

    public function toResponse()
    {
        $response = ['uuid' => $this->subJournalUuid];
        $response['code'] = $this->code;
        $response['names'] = [];
        foreach ($this->names as $name) {
            $response['names'][] = $name->toResponse();
        }
        if ($this->extra !== null) {
            $response['extra'] = $this->extra;
        }
        $response['revision'] = Revision::create($this->revision, $this->updated_at);
        $response['createdAt'] = $this->created_at;
        $response['updatedAt'] = $this->updated_at;

        return $response;
    }

}
