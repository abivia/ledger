<?php

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;

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
    public int $pageSize;

    /**
     * @var Section[] Reporting section definitions.
     */
    public array $sections = [];

    public function __construct()
    {
        $this->account = new Account();
        $this->domain = new Domain();
        $this->entry = new Entry();
        $this->language = new Language();
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
                ->bind(self::class);
        }
        return self::$hydrator->hydrate($this, $config, $options);
    }

}
