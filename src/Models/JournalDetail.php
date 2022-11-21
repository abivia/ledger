<?php

namespace Abivia\Ledger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Line item in a journal entry.
 *
 * @property LedgerAccount $account linked account
 * @property string $amount The detail amount as a BCD string.
 * @property LedgerBalance[] $balances Balance records for this detail.
 * @property int $journalDetailId Primary key.
 * @property int $journalEntryId The JournalEntry ID that this detail belongs to.
 * @property string $journalReferenceUuid Optional reference to an associated entity.
 * @property string $ledgerUuid The ledger account this applies to.
 * @mixin Builder
 * @mixin \Illuminate\Database\Query\Builder
 */
class JournalDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'journalDetailId';

    public $timestamps = false;

    /**
     * Get the Account associated with this Detail.
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            LedgerAccount::class, 'ledgerUuid', 'ledgerUuid'
        );
    }

    /**
     * Get the balances for the account connected to this detail.
     * @return HasMany
     */
    public function balances(): HasMany
    {
        return $this->hasMany(
            LedgerBalance::class, 'ledgerUuid', 'ledgerUuid'
        );
    }

    /**
     * Get the Journal entry that contains this detail.
     * @return BelongsTo
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class, 'journalEntryId', 'journalEntryId'
        );
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
