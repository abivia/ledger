<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Http\Controllers\LedgerCurrencyController;

class CurrencyQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last currency in the previous page.
     */
    public string $after;

    public function run(): array
    {
        $controller = new LedgerCurrencyController();
        $currencies = [];
        foreach ($controller->query($this, $this->opFlags) as $entry) {
            $currencies[] = $entry->toResponse([]);
        }
        $response = ['currencies' => $currencies];

        return $response;
    }
}
