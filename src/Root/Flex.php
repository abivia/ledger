<?php

namespace Abivia\Ledger\Root;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Abivia\Ledger\Root\Rules\LedgerRules;

class Flex implements Hydratable
{
    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;

    /**
     * @var LedgerRules rules governing Ledger.
     */
    public LedgerRules $rules;

    /**
     * @var string Salt for generation of revision codes.
     */
    public string $salt;

    /**
     * @inheritDoc
     */
    public function hydrate($config, ?array $options = []): bool
    {
        // Some legacy flex values were improperly encoded as 'null'.
        if ($config === null || $config === 'null') {
            return true;
        }
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }

}
