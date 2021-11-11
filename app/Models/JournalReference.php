<?php

namespace App\Models;

use App\Models\Messages\Ledger\EntityRef;
use App\Models\Messages\Ledger\Reference;
use App\Traits\UuidPrimaryKey;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Link to an external account entity (customer, vendor, etc.).
 *
 * @property string $code External identifier.
 * @property string $extra Application specific information.
 * @property string $journalReferenceUuid UUID primary key.
 * @mixin Builder
 */
class JournalReference extends Model
{
    use HasFactory, UuidPrimaryKey;

    protected $fillable = ['code', 'extra'];
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
        $instance->save();
        $instance->refresh();

        return $instance;
    }

    /**
     * @param EntityRef $reference
     * @return Builder
     * @throws Exception
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     */
    public static function findWith(EntityRef $reference): Builder
    {
        if (isset($reference->uuid)) {
            $finder = self::where('journalReferenceUuid', $reference->uuid);
        } elseif (isset($reference->code)) {
            $finder = self::where('code', $reference->code);
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
        $response['createdAt'] = $this->created_at;
        $response['updatedAt'] = $this->updated_at;

        return $response;
    }

}
