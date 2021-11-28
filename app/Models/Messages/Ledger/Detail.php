<?php
declare(strict_types=1);

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class Detail extends Message
{
    public const MAX_DECIMALS = 30;

    public string $amount;
    public EntityRef $account;
    public string $credit;
    public string $debit;
    /**
     * @var int Amount sign (set on validation, amount is unchanged).
     */
    public int $signTest;

    protected static array $copyable = [
        ['amount', self::OP_ADD | self::OP_UPDATE],
        //['account', self::OP_ADD | UPDATE],
        ['debit', self::OP_ADD | self::OP_UPDATE],
        ['credit', self::OP_ADD | self::OP_UPDATE],
        //['reference', self::OP_ADD | self::OP_UPDATE],
    ];

    /**
     * @var LedgerAccount|null Related account, populated during operations.
     */
    private ?LedgerAccount $ledgerAccount = null;

    public ?EntityRef $reference = null;

    public function __construct(EntityRef $account, string $amount) {
        $this->account = $account;
        $this->amount = $amount;
    }

    public function findAccount(): ?LedgerAccount
    {
        if ($this->ledgerAccount === null) {
            $this->ledgerAccount = LedgerAccount::findWith($this->account)->first();
            if ($this->ledgerAccount !== null) {
                $this->account->uuid = $this->ledgerAccount->ledgerUuid;
            }
        }
        return $this->ledgerAccount;
    }

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $detail = new static();
        $detail->copy($data, $opFlags);
        if (isset($data['accountCode'])) {
            $detail->account = new EntityRef();
            $detail->account->code = $data['accountCode'];
        }
        if (isset($data['accountUuid'])) {
            $detail->account ??= new EntityRef();
            $detail->account->code = $data['accountUuid'];
        }
        if (isset($data['reference'])) {
            $detail->reference = EntityRef::fromRequest($data['reference'], $opFlags);
        }
        if ($opFlags & self::F_VALIDATE) {
            $detail->validate($opFlags);
        }
        return $detail;
    }

    public function normalizeAmount(int $digits)
    {
        $this->amount = bcadd('0', $this->amount, $digits);

        return $this->amount;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): Message
    {
        $errors = [];
        if (isset($this->account)) {
            try {
                $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
                $this->account->validate($opFlags, $codeFormat);
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
        } else {
            $errors[] = __('the code property is required');
        }
        $multiplier = '1';
        if (isset($this->amount)) {
            if (isset($this->credit) || isset($this->debit)) {
                $errors[] = __('Transaction cannot have amount and debit/credit..');
            }
        } else {
            if (isset($this->credit) === isset($this->debit)) {
                $errors[] = __('Exactly one of debit or credit must be set.');
            } elseif (isset($this->debit)) {
                $this->amount = $this->debit;
                $multiplier = '-1';
                $this->debit = '';
            } elseif (isset($this->credit)) {
                $this->amount = $this->credit;
                $this->credit = '';
            }
        }
        if (!preg_match('/^[+-]?[0-9]*\.?[0-9]*$/', $this->amount)) {
            $errors[] = __('Transaction amount must be numeric');
        }
        $this->amount = rtrim(bcmul($multiplier, $this->amount, self::MAX_DECIMALS), '0');
        $this->signTest = bccomp($this->amount, '0', self::MAX_DECIMALS);
        if ($this->signTest === 0) {
            $errors[] = __('Transaction amount must be nonzero');
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
