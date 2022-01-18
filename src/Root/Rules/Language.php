<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Illuminate\Support\Facades\App;

class Language implements Hydratable
{
    /**
     * @var string Language to use when not provided in a message.
     */
    public string $default;

    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;


    public function __construct()
    {
        $this->default = App::getLocale();
    }

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
