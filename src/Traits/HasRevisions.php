<?php
declare(strict_types=1);

namespace Abivia\Ledger\Traits;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Revision;

trait HasRevisions
{
    /**
     * @var string|null Computed revision code for the current model.
     */
    private ?string $revisionHashCached;

    private function batchLogFetch()
    {
        Revision::saveBatchFetch($this->getBatchKey(), $this->revisionHash);
    }

    /**
     * Throw a Breaker exception if the request revision doesn't match the stored value.
     * @param ?string $revision
     * @throws Breaker
     */
    public function checkRevision(?string $revision)
    {
        // Save the batch revision on the first check
        $batchKey = $this->getBatchKey();
        if ($revision === '&') {
            $revision = Revision::checkFetch($batchKey);
        }
        Revision::saveBatchRevision($batchKey, $this->revisionHash);

        if (
            $revision !== $this->revisionHash
            && !(Revision::checkBatchRevision($batchKey, $revision, $this->revisionHash))
        ) {
            throw Breaker::withCode(Breaker::BAD_REVISION);
        }
    }

    public function clearRevisionCache(): void
    {
        $this->revisionHashCached = null;
    }

    private function getBatchKey(): string{
        return static::class . ':' . $this->{$this->primaryKey};
    }

    public function getRevisionHash()
    {
        if (!isset($this->revisionHashCached)) {
            $this->revisionHashCached = Revision::create(
                $this->revision, $this->updated_at
            );
        }

        return $this->revisionHashCached;
    }

    public function refresh()
    {
        parent::refresh();
        Revision::saveBatchRevision($this->getBatchKey(), $this->getRevisionHash());
    }

}
