<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use ErrorException;
use Exception;
use ValueError;

/**
 * Account detail in a journal entry.
 *
 * @property-read int $signTest
 */
class Detail extends Message
{
    public const MAX_DECIMALS = 30;

    /**
     * @var string A valid amount for this Currency.
     */
    public string $amount;

    /**
     * @var EntityRef A reference to the affected account.
     */
    public EntityRef $account;

    /**
     * @var string A valid amount for this Currency.
     */
    public string $credit;

    /**
     * @var string A valid amount for this Currency.
     */
    public string $debit;

    protected static array $copyable = [
        ['amount', self::OP_ADD | self::OP_UPDATE],
        //['code', self::OP_ADD | UPDATE],
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
     * @var int The sign of the amount (+1 or -1), set on validation.
     */
    protected int $signTest;

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
     * Property get.
     * @param $name
     * @return int|null
     */
    public function __get($name)
    {
        if ($name === 'signTest') {
            return $this->signTest;
        }
        return null;
    }

    /**
     * Find the ledger account associated with this detail record.
     *
     * @throws Breaker
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
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $detail = new static();
        $detail->copy($data, $opFlags);
        if (isset($data['code'])) {
            $detail->account = new EntityRef();
            $detail->account->code = $data['code'];
        }
        if (isset($data['uuid'])) {
            $detail->account ??= new EntityRef();
            $detail->account->code = $data['uuid'];
        }
        if (isset($data['reference'])) {
            if (!is_array($data['reference'])) {
                $data['reference'] = ['uuid' => $data['reference']];
            }
            $detail->reference = Reference::fromArray($data['reference'], $opFlags);
        }
        if ($opFlags & self::F_VALIDATE) {
            $detail->validate($opFlags);
        }
        return $detail;
    }

    /**
     * Make the amount have the requested number of decimal places.
     *
     * @param ?int $decimals
     * @return string
     * @throws Breaker
     */
    public function normalizeAmount(?int $decimals = null): string
    {
        try {
            $multiplier = '1';
            if (
                isset($this->credit)
                && bccomp($this->credit, '0', self::MAX_DECIMALS) === 0
            ) {
                unset($this->credit);
            }
            if (
                isset($this->debit)
                && bccomp($this->debit, '0', self::MAX_DECIMALS) === 0
            ) {
                unset($this->debit);
            }
            if (isset($this->amount)) {
                if (isset($this->credit) || isset($this->debit)) {
                    throw Breaker::withCode(
                        Breaker::BAD_REQUEST,
                        [__('Transaction cannot have amount and debit/credit..')]
                    );
                }
            } else {
                if (isset($this->credit) === isset($this->debit)) {
                    throw Breaker::withCode(
                        Breaker::BAD_REQUEST,
                        [__('Exactly one of amount, debit, or credit must be set and non-zero.')]
                    );
                } elseif (isset($this->debit)) {
                    $this->amount = $this->debit;
                    $multiplier = '-1';
                    unset($this->debit);
                } elseif (isset($this->credit)) {
                    $this->amount = $this->credit;
                    unset($this->credit);
                }
            }
            $this->amount = bcmul($multiplier, $this->amount, $decimals ?? self::MAX_DECIMALS);
            if ($decimals === null) {
                $this->amount = rtrim($this->amount, '0');
            }
        } catch (ErrorException $exception) {
            // PHP 7.4 throws this
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__('Transaction amount must be numeric.')]
            );
        } catch (ValueError $exception) {
            // PHP 8+ throws this
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__('Transaction amount must be numeric.')]
            );

        }

        return $this->amount;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags): self
    {
        $opFlags ??= $this->getOpFlags();
        $errors = [];
        if (isset($this->account)) {
            try {
                $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
                $this->account->validate($opFlags, $codeFormat);
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
        } else {
            $errors[] = __('the account code property is required');
        }
        try {
            $this->normalizeAmount();
        } catch (Breaker $exception) {
            Merge::arrays($errors, $exception->getErrors());
        }
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
