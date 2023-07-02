<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use Exception;

/**
 * Rules governing ledger behaviour.
 */
class LedgerRules implements Hydratable
{
    /**
     * @var Account Properties for ledger accounts.
     */
    public Account $account;

    /**
     * @var array Extension data supplied by the application
     */
    public array $appAttributes = [];

    /**
     * @var Batch Batch rules.
     */
    public Batch $batch;

    /**
     * @var Domain Domain properties.
     */
    public Domain $domain;

    /**
     * @var Entry Journal entry rules.
     */
    public Entry $entry;

    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;

    /**
     * @var Language Language settings.
     */
    public Language $language;

    /**
     * @var string The Ledger's opening date.
     */
    public string $openDate;

    /**
     * @var int Default page size for paginated requests.
     */
    public int $pageSize = 25;

    /**
     * @var Section[] Reporting section definitions.
     */
    public array $sections = [];

    public function __construct()
    {
        $this->account = new Account();
        $this->batch = new Batch();
        $this->domain = new Domain();
        $this->entry = new Entry();
        $this->language = new Language();
    }

    public function __get(string $name)
    {
        if (!str_starts_with($name, '_') || !isset($this->appAttributes[$name])) {
            throw new Exception("Undefined property $name");
        }
        return $this->appAttributes[$name];
    }

    public function __set($name, $value)
    {
        if (!str_starts_with($name, '_')) {
            throw new Exception("Undefined property $name");
        }
        $this->appAttributes[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make()
                ->addProperty(
                    Property::make('sections')
                        ->bind(Section::class)
                        ->key()
                )
                ->addProperty(
                    Property::make('appAttributes')
                        ->toArray()
                )
                ->bind(self::class);
        }

        // Handle application extensions.
        if (is_string($config)) {
            $config = self::$hydrator::parse($config, $options);
        }
        if (!is_string($config)) {
            foreach ($config as $key => $value) {
                if (str_starts_with($key, '_')) {
                    $this->$key = $value;
                    unset($config[$key]);
                }

            }
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }

}
