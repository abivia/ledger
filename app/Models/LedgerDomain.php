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
 * @property string $code The unique domain code.
 * @property Carbon $created_at When the record was created.
 * @property string $currencyDefault The default currency.
 * @property string $domainUuid Primary key.
 * @property string $extra Application defined information.
 * @property string $flex JSON-encoded additional system information (e.g. supported currencies).
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property bool $subJournals Set if the domain uses sub-journals.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class LedgerDomain extends Model
{
    use HasFactory, UuidPrimaryKey;

    protected $dateFormat = 'Y-m-d H:i:s.u';
    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'domainUuid';
}
