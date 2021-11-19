<?php
declare(strict_types=1);

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\Messages\Message;

class Reference extends Message
{
    public ?string $code;
    /**
     * @var mixed
     */
    public $extra;
    public ?string $journalReferenceUuid;
    public ?string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $reference = new static();
        if (isset($data['code'])) {
            $reference->code = $data['code'];
        }
        if (isset($data['extra'])) {
            $reference->extra = $data['extra'];
        }
        if (isset($data['uuid'])) {
            $reference->journalReferenceUuid = $data['uuid'];
        }
        if ($opFlags & self::OP_UPDATE) {
            if (isset($data['toCode'])) {
                $reference->toCode = $data['toCode'];
            }
        }
        if ($opFlags & self::FN_VALIDATE) {
            $reference->validate($opFlags);
        }

        return $reference;
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
