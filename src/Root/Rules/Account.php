<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;

class Account implements Hydratable
{
    /**
     * @var string A regular expression governing what account codes look like.
     */
    public string $codeFormat;

    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;

    /**
     * @var bool Allow posting to category accounts (usually a bad idea).
     */
    public bool $postToCategory = false;

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
