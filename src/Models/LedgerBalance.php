<?php

namespace Abivia\Ledger\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger balance (by currency)
 *
 * @property string $balance The current balance, a BCD string.
 * @property Carbon $created_at When the record was created.
 * @property string $currency The currency code for this balance
 * @property string $domainUuid The organizational unit (department/division/etc.) UUID
 * @property int $id Primary key
 * @property string $ledgerUuid ID of the ledger this balance applies to.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class LedgerBalance extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['balance', 'currency', 'domainUuid', 'ledgerUuid'];
}
