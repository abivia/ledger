<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;

class Entry implements Hydratable
{
    /**
     * @var Hydrator Configuration loader.
     */
    public static Hydrator $hydrator;

    /**
     * @var bool Review status on create. Default is to leave transactions as not reviewed.
     */
    public bool $reviewed = false;

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
