<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Multilingual support for account names
 *
 * @method static LedgerName create(array $attributes) Provided by model.
 * @property DateTime $created_at When the record was created.
 * @property int $id Primary key
 * @property string $language The language code for this name.
 * @property string $name The ledger entity name.
 * @property string $ownerUuid ID of the entity this name applies to.
 * @property DateTime $updated_at When the record was updated.
 */
class LedgerName extends Model
{
    use HasFactory;

    protected $fillable = ['language', 'name', 'ownerUuid'];

    public function named()
    {
        return $this->morphTo();
    }

    public function toResponse(): array
    {
        return [
            'name' => $this->name,
            'language' => $this->language,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

}
