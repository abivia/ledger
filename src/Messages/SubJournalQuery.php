<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Http\Controllers\SubJournalController;

class SubJournalQuery extends Paginated
{
    /**
     * @var string For pagination, a reference to the last sub-journal in the previous page.
     */
    public string $after;

    public function run(): array
    {
        $controller = new SubJournalController();
        $subJournals = [];
        foreach ($controller->query($this, $this->opFlags) as $entry) {
            $subJournals[] = $entry->toResponse([]);
        }
        return ['journals' => $subJournals];
    }
}
