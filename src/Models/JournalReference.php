<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Reference;
use Abivia\Ledger\Traits\HasRevisions;
use Abivia\Ledger\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Link to an external account entity (customer, vendor, etc.).
 *
 * @property string $code External identifier.
 * @property Carbon $created_at
 * @property string $domainUuid The domain this reference exists in.
 * @property string $extra Application specific information.
 * @property string $journalReferenceUuid UUID primary key.
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property Carbon $updated_at
 * @mixin Builder
 */
class JournalReference extends Model
{
    use HasFactory, HasRevisions, UuidPrimaryKey;

    protected $casts = [
        'revision' => 'datetime',
    ];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['code', 'domainUuid', 'extra'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'journalReferenceUuid';

    public static function createFromMessage(Reference $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        $instance->domainUuid = $message->domain->uuid;
        $instance->save();
        $instance->refresh();

        return $instance;
    }

    /**
     * @param Reference $reference
     * @return Builder
     * @throws Exception
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     */
    public static function findWith(Reference $reference): Builder
    {
        $finder = self::where('domainUuid', $reference->domain->uuid);
        if (isset($reference->journalReferenceUuid)) {
            $finder = $finder->where('journalReferenceUuid', $reference->journalReferenceUuid);
        } elseif (isset($reference->code)) {
            $finder = $finder->where('code', $reference->code);
        } else {
            throw new Exception('Reference must have either code or uuid entries');
        }

        return $finder;
    }

    public function toResponse(): array
    {
        $response = ['uuid' => $this->journalReferenceUuid];
        $response['code'] = $this->code;
        if ($this->extra !== null) {
            $response['extra'] = $this->extra;
        }
        $response['revision'] = Revision::create($this->revision, $this->updated_at);
        $response['createdAt'] = $this->created_at;
        $response['updatedAt'] = $this->updated_at;

        return $response;
    }

}
