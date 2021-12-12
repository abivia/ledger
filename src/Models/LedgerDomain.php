<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Traits\HasRevisions;
use Abivia\Ledger\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domains assigned within the ledger.
 *
 * @property string $code The unique domain code.
 * @property Carbon $created_at When the record was created.
 * @property string $currencyDefault The default currency.
 * @property string $domainUuid Primary key.
 * @property string $extra Application defined information.
 * @property string $flex JSON-encoded additional system information (e.g. supported currencies).
 * @property LedgerName[] $names Associated names.
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property bool $subJournals Set if the domain uses sub-journals.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class LedgerDomain extends Model
{
    use HasFactory, HasRevisions, UuidPrimaryKey;

    protected $casts = [
        'revision' => 'datetime',
    ];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = [
        'code', 'currencyDefault', 'extra', 'subJournals'
    ];
    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'domainUuid';

    public static function createFromMessage(Domain $message): self
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

    /**
     * @param EntityRef $entityRef
     * @return Builder
     * @throws Exception
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     */
    public static function findWith(EntityRef $entityRef): Builder
    {
        if (isset($entityRef->uuid) && $entityRef->uuid !== null) {
            $finder = self::where('domainUuid', $entityRef->uuid);
        } elseif (isset($entityRef->code)) {
            $finder = self::where('code', $entityRef->code);
        } else {
            throw new Exception('Domain reference must have either code or uuid entries');
        }

        return $finder;
    }

    public function names(): HasMany
    {
        return $this->hasMany(LedgerName::class, 'ownerUuid', 'domainUuid');
    }

    public function toResponse()
    {
        $response = ['uuid' => $this->domainUuid];
        $response['code'] = $this->code;
        $response['currency'] = $this->currencyDefault;
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
