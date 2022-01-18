<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Illuminate\Support\Facades\App;

class Name implements Hydratable
{
    private static Hydrator $hydrator;
    public string $language;
    public string $name;

    public function __construct(string $name = null, string $language = null)
    {
        if ($name !== null) {
            $this->name = $name;
            if ($language === null) {
                $this->language = App::getLocale();
            } else {
                $this->language = $language;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make(self::class);
        }

        $success = self::$hydrator->hydrate($this, $config, $options);
        if ($success && !isset($this->language)) {
            $this->language = App::getLocale();
        }

        return $success;
    }

}
