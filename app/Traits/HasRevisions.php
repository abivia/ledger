<?php
declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\Breaker;
use App\Helpers\Revision;

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
