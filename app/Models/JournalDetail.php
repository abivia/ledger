<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Line item ina journal entry.
 *
 * @property string $amount The detail amount as a BCD string.
 * @property string $journalEntityUuid Optional reference to an associated entity.
 * @property int $journalEntryId The JournalEntry ID that this detail belongs to.
 * @property string $ledgerUuid The ledger account this applies to.
 */
class JournalDetail extends Model
{
    use HasFactory;
    protected $primaryKey = 'journalDetailId';

    public $timestamps = false;

    public function account(string $ledgerUuid): self
    {
        if (!LedgerAccount::exists($ledgerUuid)) {
            throw new RuntimeException("Invalid account code: {$ledgerUuid}.");
        }
        $this->ledgerUuid = $ledgerUuid;

        return $this;
    }

    public function detail($entity, string $ledgerUuid, string $amount): self
    {
        if (is_object($entity)) {
            $this->ledgerUuid = $entity->getUuid();
        } else {
            $this->ledgerUuid = $entity;
        }
        $this->account($ledgerUuid);
        $this->amount = (string) $amount;

        return $this;
    }

    public function sameAccount(JournalDetail $detail)
    {
        return $this->ledgerUuid === $detail->ledgerUuid
            && $this->journalEntityUuid === $detail->journalEntityUuid;
    }

}
