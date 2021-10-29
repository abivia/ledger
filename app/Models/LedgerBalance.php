<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger balance (by currency)
 *
 * @property string $balance The current balance, a BCD string.
 * @property DateTime $created_at When the record was created.
 * @property string $currency The currency code for this balance
 * @property string $domain The organizational unit (department/division/etc.) identifier
 * @property int $id Primary key
 * @property string $ledgerUuid ID of the ledger this balance applies to.
 * @property DateTime $updated_at When the record was updated.
 */
class LedgerBalance extends Model
{
    use HasFactory;
}
