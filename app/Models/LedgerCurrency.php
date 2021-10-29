<?php

namespace App\Models;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Currencies available to the ledger.
 *
 * @method static LedgerCurrency create(array $attributes) Provided by model.
 * @property DateTime $created_at When the record was created.
 * @property string $code The currency code.
 * @property int $decimals The number of decimals to carry.
 * @property DateTime $revision Revision timestamp to detect race condition on update.
 * @property DateTime $updated_at When the record was updated.
 */
class LedgerCurrency extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'decimals'];
    public $incrementing = false;
    protected $keyType = 'string';

    public $primaryKey = 'currency';
}
