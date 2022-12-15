<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;

trait HasNames
{
    /**
     * @var array<Name> A list of `Name` messages.
     */
    public array $names = [];

    /**
     * Load the name and/or names properties.
     *
     * @param array $data
     * @param int $opFlags
     * @return array
     */
    protected function loadNames(array $data, int $opFlags): array
    {
        $errors = [];
        try {
            if (
                $opFlags & (
                    Message::OP_ADD | Message::OP_CREATE
                    | Message::OP_QUERY | Message::OP_UPDATE
                )
            ) {
                $nameList = $data['names'] ?? [];
                if (isset($data['name'])) {
                    array_unshift($nameList, ['name' => $data['name']]);
                }
                $this->names = Name::fromRequestList(
                    $nameList, $opFlags, ($opFlags & Message::OP_ADD) ? 1 : 0
                );
            }
        } catch (Breaker $exception) {
            $errors = $exception->getErrors();
        }

        return $errors;
    }
}
