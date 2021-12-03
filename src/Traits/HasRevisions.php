<?php
declare(strict_types=1);

namespace Abivia\Ledger\Traits;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Revision;

trait HasRevisions
{
    /**
     * @param ?string $revision
     * @throws Breaker
     */
    public function checkRevision(?string $revision)
    {
        if ($revision !== Revision::create($this->revision, $this->updated_at)) {
            throw Breaker::withCode(Breaker::BAD_REVISION);
        }
    }

}
