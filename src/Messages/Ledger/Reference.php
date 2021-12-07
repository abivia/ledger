<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Messages\Message;
use Exception;

class Reference extends Message
{
    public string $code;
    protected static array $copyable = [
        'code', 'extra',
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];


    /**
     * @var mixed
     */
    public $extra;
    public string $journalReferenceUuid;
    public string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $reference = new static();
        $reference->copy($data, $opFlags);
        if (isset($data['uuid'])) {
            $reference->journalReferenceUuid = $data['uuid'];
        }
        if ($opFlags & self::F_VALIDATE) {
            $reference->validate($opFlags);
        }

        return $reference;
    }

    /**
     * Verify that the reference is valid, filling in the UUID if missing.
     * @throws Breaker
     * @throws Exception
     */
    public function lookup(): self
    {
        /** @var JournalReference $journalReference */
        $journalReference = JournalReference::findWith($this)->first();
        if ($journalReference === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__(
                    'Reference :code does not exist.',
                    ['code' => $this->code ?? '[undefined]']
                )]
            );
        }
        if (!isset($this->journalReferenceUuid)) {
            $this->journalReferenceUuid = $journalReference->journalReferenceUuid;
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
        if (!isset($this->code)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [__('the code property is required')]
            );
        }

        return $this;
    }
}
