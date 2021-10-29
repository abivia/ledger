<?php

namespace App\Models;

use App\Traits\UuidPrimaryKey;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Domains assigned within the ledger.
 *
 * @method static SubJournal create(array $attributes) Provided by model.
 * @property string $code Unique identifier for the sub-journal.
 * @property DateTime $created_at When the record was created.
 * @property string $extra Application defined information.
 * @property string $name The name/title of this journal.
 * @property DateTime $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid Identifier for this journal.
 * @property DateTime $updated_at When the record was updated.
 */
class SubJournal extends Model
{
    use HasFactory, UuidPrimaryKey;

    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'subJournalUuid';
}
