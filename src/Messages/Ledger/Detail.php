<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;
use Exception;

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

    public Reference $reference;

    /**
     * Detail constructor.
     *
     * @param EntityRef|null $account
     * @param string|null $amount
     */
    public function __construct(?EntityRef $account = null, ?string $amount = null) {
        if ($account !== null) {
            $this->account = $account;
        }
        if ($amount !== null) {
            $this->amount = $amount;
        }
    }

    /**
     * Find the ledger account associated with this detail record.
     *
     * @throws Exception
     */
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
            $detail->reference = Reference::fromRequest($data['reference'], $opFlags);
        }
        if ($opFlags & self::F_VALIDATE) {
            $detail->validate($opFlags);
        }
        return $detail;
    }

    /**
     * Make the amount have the requested number of decimal places.
     *
     * @param int $decimals
     * @return string
     */
    public function normalizeAmount(int $decimals): string
    {
        $this->amount = bcadd('0', $this->amount, $decimals);

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
