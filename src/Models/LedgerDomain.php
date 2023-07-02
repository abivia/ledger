<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Traits\CommonResponseProperties;
use Abivia\Ledger\Traits\HasRevisions;
use Abivia\Ledger\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HigherOrderCollectionProxy;

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
 * @property Carbon $revision Revision hash to detect race condition on update.
 * @property bool $subJournals Set if the domain uses sub-journals.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class LedgerDomain extends Model
{
    use CommonResponseProperties, HasFactory, HasNames, HasRevisions, UuidPrimaryKey;

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
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     * @throws Breaker
     */
    public static function findWith(EntityRef $entityRef): Builder
    {
        if (isset($entityRef->uuid)) {
            $finder = self::where('domainUuid', $entityRef->uuid);
        } elseif (isset($entityRef->code)) {
            $finder = self::where('code', $entityRef->code);
        } else {
            throw Breaker::withCode(
                Breaker::INVALID_DATA,
                [__('Domain reference must have either code or uuid entries')]
            );
        }

        return $finder;
    }

    /**
     * Create a response array.
     * @return string[]
     * @throws Exception
     */
    public function toResponse(): array
    {
        $response = ['uuid' => $this->domainUuid];
        $response['code'] = $this->code;
        $response['currency'] = $this->currencyDefault;

        return $this->commonResponses($response);
    }

}
