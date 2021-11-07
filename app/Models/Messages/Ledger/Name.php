<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class Name extends Message
{
    public string $language;
    public string $name;
    public string $ownerUuid;
    public ?string $revision;

    public static function fromRequest(array $data, int $opFlag): self
    {
        $name = new static();
        if (isset($data['name'])) {
            $name->name = $data['name'];
        }
        if (isset($data['language'])) {
            $name->language = $data['language'];
        }
        if ($opFlag & self::OP_VALIDATE) {
            $name->validate($opFlag);
        }

        return $name;
    }

    /**
     * @param array $data
     * @param int $opFlag
     * @param int $minimum
     * @return Name[]
     * @throws Breaker
     */
    public static function fromRequestList(array $data, int $opFlag, int $minimum = 0): array
    {
        $names = [];
        foreach ($data as $nameData) {
            $message = self::fromRequest($nameData, $opFlag);
            $names[$message->language] = $message;
        }
        if (count($names) < $minimum) {
            $entry = $minimum === 1 ? 'entry' : 'entries';
            throw Breaker::withCode(
                Breaker::BAD_REQUEST, ["must provide at least $minimum name $entry"]
            );
        }

        return $names;
    }

    /**
     * @param int $opFlag
     * @return Name
     * @throws Breaker
     */
    public function validate(int $opFlag): self
    {
        if (!isset($this->name)) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST, [__("must include name property")]
            );
        }
        $this->language ??= LedgerAccount::rules()->language->default;

        return $this;
    }
}
