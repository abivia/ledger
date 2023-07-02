<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Http\Controllers\LedgerDomainController;

class DomainQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last domain in the previous page.
     */
    public string $after;

    public function run(): array
    {
        $controller = new LedgerDomainController();
        $domains = [];
        foreach ($controller->query($this, $this->opFlags) as $entry) {
            $domains[] = $entry->toResponse([]);
        }

        return ['domains' => $domains];
    }
}
