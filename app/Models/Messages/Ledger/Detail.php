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

    public string $amount = '0';
    public EntityRef $account;
    /**
     * @var int Amount sign (set on validation, amount is unchanged).
     */
    public int $signTest;

    protected static array $copyable = [
        ['amount', self::OP_ADD | self::OP_UPDATE],
        //['account', self::OP_ADD | UPDATE],
        //['reference', self::OP_ADD | self::OP_UPDATE],
    ];

    /**
     * @var LedgerAccount|null Related account, populated during operations.
     */
    private ?LedgerAccount $ledgerAccount = null;

    public ?EntityRef $reference = null;

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
    public static function fromRequest(array $data, int $opFlag): self
    {
        $detail = new static();
        $detail->copy($data, $opFlag);
        if (isset($data['account'])) {
            $detail->account = EntityRef::fromRequest($data['account'], $opFlag);
        }
        if (isset($data['reference'])) {
            $detail->account = EntityRef::fromRequest($data['reference'], $opFlag);
        }
        if ($opFlag & self::FN_VALIDATE) {
            $detail->validate($opFlag);
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
    public function validate(int $opFlag): Message
    {
        $errors = [];
        if (isset($this->account)) {
            try {
                $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
                $this->account->validate($opFlag, $codeFormat);
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
        } else {
            $errors[] = __('the code property is required');
        }
        if (!preg_match('/^[+-]?[0-9]*\.?[0-9]*$/', $this->amount)) {
            $errors[] = __('amount must be numeric');
        }
        $this->signTest = bccomp($this->amount, '0', self::MAX_DECIMALS);
        if ($this->signTest === 0) {
            $errors[] = __('amount must be nonzero');
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
