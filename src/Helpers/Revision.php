<?php

namespace Abivia\Ledger\Helpers;

use Abivia\Ledger\Models\LedgerAccount;
use Carbon\Carbon;
use Exception;

/**
 * Support for revision signatures on API calls.
 */
class Revision
{
    /**
     * @var array An index of revisions on fetch, keyed by unique model identifiers.
     */
    private static array $batchFetch = [];
    /**
     * @var array An index of batch revisions, keyed by unique model identifiers.
     */
    private static array $batchRevisions = [];
    /**
     * @var bool Set when processing a batch
     */
    private static bool $inBatch = false;

    public static function checkBatchRevision(
        string $key, string $revision, string $newRevision
    ): bool {
        if (self::$inBatch && isset(self::$batchRevisions[$key])) {
            return self::$batchRevisions[$key][0] === $revision
                && self::$batchRevisions[$key][1] === $newRevision;
        }
        return false;
    }

    public static function checkFetch(string $batchKey): string
    {
        return self::$batchFetch[$batchKey] ?? '';
    }

    public static function clearBatch()
    {
        self::$batchFetch = [];
        self::$batchRevisions = [];
    }

    /**
     * Create a revision signature based on a hash of the ledger's salt and the
     * server-based last record update timestamp, with a fallback for database
     * managers that don't support server timestamps.
     *
     * @param Carbon|null $revision The database server maintained timestamp.
     * @param Carbon $fallback The Laravel maintained timestamp.
     *
     * @return string
     * @throws Exception
     */
    public static function create(?Carbon $revision, Carbon $fallback): string
    {
        if (!LedgerAccount::hasRoot() || !isset(LedgerAccount::root()->flex->salt)) {
            return '';
        }
        $use = $revision ?? $fallback;
        return hash('ripemd256', LedgerAccount::root()->flex->salt . $use->toJSON());
    }

    public static function endBatch()
    {
        self::clearBatch();
        self::$inBatch = false;
    }

    public static function isInBatch(): bool
    {
        return self::$inBatch;
    }

    public static function saveBatchFetch(string $batchKey, string $revisionHash)
    {
        if (self::$inBatch) {
            self::$batchFetch[$batchKey] = $revisionHash;
            if (isset(self::$batchRevisions[$batchKey])) {
                self::$batchRevisions[$batchKey][1] = $revisionHash;
            }
        }
    }

    /**
     * Save a model's revision hashes through the processing of a batch.
     *
     * @param string $key A unique identifier for the model.
     * @param string $revision The revision hash.
     * @return void
     */
    public static function saveBatchRevision(string $key, string $revision)
    {
        if (!self::$inBatch || $revision === '') {
            return;
        }
        if (!isset(self::$batchRevisions[$key])) {
            // Save the original revision
            self::$batchRevisions[$key] = [$revision, $revision];
        } else {
            // Save the latest revision
            self::$batchRevisions[$key][1] = $revision;
        }
    }

    public static function startBatch():void
    {
        self::clearBatch();
        self::$inBatch = true;
    }

}
