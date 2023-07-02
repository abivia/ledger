<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Http\Controllers\SubJournalController;
use Abivia\Ledger\Messages\Message;

class SubJournal extends Message
{
    use HasCodes, HasNames;

    protected static array $copyable = [
        'code', 'extra',
        ['revision', self::OP_DELETE | self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        //'uuid',
    ];

    /**
     * @var string
     */
    public string $extra;

    public string $revision;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $subJournal = new static();
        $subJournal->copy($data, $opFlags);
        $subJournal->loadNames($data, $opFlags);
        if ($opFlags & self::F_VALIDATE) {
            $subJournal->validate($opFlags);
        }

        return $subJournal;
    }

    public function run(): array
    {
        $controller = new SubJournalController();
        $subJournal = $controller->run($this);
        if ($this->opFlags & Message::OP_DELETE) {
            $response = ['success' => true];
        } else {
            $response = ['journal' => $subJournal->toResponse()];
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $errors = $this->validateCodes($opFlags);
        if ($opFlags & self::OP_ADD && count($this->names) === 0) {
            $errors[] = __('at least one name property is required');
        }
        try {
            foreach ($this->names as $name) {
                $name->validate($opFlags);
            }
        } catch (Breaker $exception) {
            Merge::arrays($errors, $exception->getErrors());
        }
        if ($opFlags & self::OP_UPDATE) {
            $this->requireRevision($errors);
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
