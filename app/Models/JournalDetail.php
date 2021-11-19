<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Line item in a journal entry.
 *
 * @property string $amount The detail amount as a BCD string.
 * @property int $journalDetailId Primary key.
 * @property int $journalEntryId The JournalEntry ID that this detail belongs to.
 * @property string $journalReferenceUuid Optional reference to an associated entity.
 * @property string $ledgerUuid The ledger account this applies to.
 * @mixin Builder
 */
class JournalDetail extends Model
{
    use HasFactory;
    protected $primaryKey = 'journalDetailId';

    public $timestamps = false;

    public function balances(): HasMany
    {
        return $this->hasMany(LedgerBalance::class, 'ledgerUuid', 'ledgerUuid');
    }

    public function toResponse(): array
    {
        $ledgerAccount = LedgerAccount::find($this->ledgerUuid);
        $response = [];
        $response['accountCode'] = $ledgerAccount->code;
        $response['accountUuid'] = $ledgerAccount->ledgerUuid;
        if ($this->journalReferenceUuid !== null) {
            $response['reference'] = $this->journalReferenceUuid;
        }
        $response['amount'] = $this->amount;

        return $response;
    }

}
