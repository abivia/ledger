<?php

namespace App\Models;

use App\Traits\UuidPrimaryKey;
use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Domains assigned within the ledger.
 *
 * @property string $code The unique domain code.
 * @property DateTime $created_at When the record was created.
 * @property string $currencyDefault The default currency.
 * @property string $domainUuid Primary key.
 * @property string $extra Application defined information.
 * @property string $flex JSON-encoded additional system information (e.g. supported currencies).
 * @property DateTime $revision Revision timestamp to detect race condition on update.
 * @property bool $subJournals Set if the domain uses sub-journals.
 * @property DateTime $updated_at When the record was updated.
 */
class LedgerDomain extends Model
{
    use HasFactory, UuidPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';
    public $primaryKey = 'domainUuid';
}
