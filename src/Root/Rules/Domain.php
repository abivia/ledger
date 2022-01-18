<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;

class Domain implements Hydratable
{
    /**
     * @var string Name of the domain to use when not provided in a message.
     */
    public string $default;

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
