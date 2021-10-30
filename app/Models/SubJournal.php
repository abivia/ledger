<?php

namespace App\Models;

use App\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Domains assigned within the ledger.
 *
 * @method static SubJournal create(array $attributes) Provided by model.
 * @property string $code Unique identifier for the sub-journal.
 * @property Carbon $created_at When the record was created.
 * @property string $extra Application defined information.
 * @property string $name The name/title of this journal.
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property string $subJournalUuid Identifier for this journal.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class SubJournal extends Model
{
    use HasFactory, UuidPrimaryKey;

    protected $dateFormat = 'Y-m-d H:i:s.u';
    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'subJournalUuid';
}
