<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;

class Batch implements Hydratable
{
    /**
     * @var bool Set if report generation is allowed in a batch
     */
    public bool $allowReports = true;
    /**
     * @var int Limit to the number of transactions in a batch (zero if none)
     */
    public int $limit = 0;

    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;

    /**
     * @inheritDoc
     */
    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }

}
