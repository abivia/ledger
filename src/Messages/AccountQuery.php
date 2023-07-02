<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Http\Controllers\LedgerAccountController;

class AccountQuery extends Paginated
{

    /**
     * @var EntityRef For pagination, a reference to the last account in the previous page.
     */
    public EntityRef $after;

    protected static array $copyable = [
        'limit',
        'range',
        'rangeEnding',
    ];

    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $query = parent::fromArray($data, $opFlags);
        if (isset($data['after'])) {
            $query->after = EntityRef::fromArray($data['after'], $opFlags);
        }
        if (isset($data['domain'])) {
            $query->domain = EntityRef::fromMixed($data['domain']);
        }

        return $query;
    }

    public function run(): array
    {
        $controller = new LedgerAccountController();
        $accounts = [];
        foreach ($controller->query($this, $this->opFlags) as $entry) {
            $accounts[] = $entry->toResponse([]);
        }
        $response['accounts'] = $accounts;

        return $response;
    }

}
