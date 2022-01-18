<?php
/** @noinspection PhpPropertyOnlyWrittenInspection */

namespace Abivia\Ledger\Root\Rules;

use Abivia\Hydration\Hydratable;
use Abivia\Hydration\Hydrator;
use Abivia\Hydration\Property;
use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use SebastianBergmann\CodeCoverage\BranchAndPathCoverageNotSupportedException;
use function __;

class Section implements Hydratable
{
    /**
     * @var string[] List of account codes belonging to this section.
     */
    public array $codes = [];

    /**
     * @var bool Set when reporting goes in the credit column.
     */
    public bool $credit;

    /**
     * @var Hydrator Configuration loader.
     */
    private static Hydrator $hydrator;

    /**
     * @var string[] Ledger IDs of accounts in this section.
     */
    public array $ledgerUuids = [];

    /**
     * @var string placeholder for simple name (no language) definitions.
     */
    private string $name;
    /**
     * @var Name[] Names of this section.
     */
    public array $names = [];

    /**
     * Construct a section from array data.
     *
     * @param $data
     * @param array|null $options Passed to validate().
     * @return static
     * @throws Breaker
     */
    public static function fromArray($data, ?array $options = []): self
    {
        $section = new static();
        foreach ($data['names'] ?? [] as $name) {
            $section->names[] = new Name(
                $name['name'] ?? null, $name['language'] ?? null
            );
        }
        if (isset($data['name'])) {
            array_unshift($section->names, new Name(
                $data['name'] ?? null,
                $data['language'] ?? null
            ));
        }
        if (isset($data['codes'])) {
            if (is_array($data['codes'])) {
                $section->codes = $data['codes'];
            } else {
                $section->codes = [$data['codes']];
            }
        }
        $section->validate($options);

        return $section;
    }

    /**
     * @inheritDoc
     */
    public function hydrate($config, ?array $options = []): bool
    {
        if (!isset(self::$hydrator)) {
            self::$hydrator = Hydrator::make()
                ->addProperties(['name'])
                ->addProperty(Property::make('codes')->key())
                ->bind(self::class);
        }
        $success = self::$hydrator->hydrate($this, $config, $options);
        if ($success) {
            if (isset($this->name)) {
                array_unshift($this->names, new Name($this->name));
                unset($this->name);
            }
            $this->validate($options);
        }

        return $success;
    }

    /**
     * Ensure the data is valid.
     *
     * @param array|null $options checkAccount [default true] will validate account codes
     * @return void
     * @throws Breaker
     */
    public function validate(?array $options = [])
    {
        $codeTrack = [];
        $this->ledgerUuids = [];
        foreach ($this->codes as $code) {
            if (isset($codeTrack[$code])) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST,
                    [__(
                        'Account code :code appears twice in sections.',
                        ['code' => $code]
                    )]
                );
            }
            if ($options['checkAccount'] ?? true) {
                $ledgerAccount = LedgerAccount::where('code', $code)
                    ->first();
                if ($ledgerAccount === null) {
                    throw Breaker::withCode(
                        Breaker::BAD_REQUEST,
                        [__('Account :code not found.', ['code' => $code])]
                    );
                }
                $this->ledgerUuids[] = $ledgerAccount->ledgerUuid;
            }
        }
    }
}
