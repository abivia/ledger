<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Helpers\Package;
use Abivia\Ledger\Root\Rules\Section;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use stdClass;
use TypeError;

/**
 * Ledger Creation request message
 *
 * @property-read $templatePath
 */
class Create extends Message
{
    use HasNames;

    public const DEFAULT_DOMAIN = 'MAIN';

    /**
     * @var Account[] A list of ledger accounts.
     */
    public array $accounts = [];

    /**
     * @var Balance[] A list of balances for the opening transaction.
     */
    public array $balances = [];

    /**
     * @var Currency[] A list of currencies supported by the ledger.
     */
    public array $currencies = [];

    /**
     * @var Domain[] A list of ledger domains (organizational units).
     */
    public array $domains = [];

    /**
     * @var SubJournal[] A list of sub-journals that can receive Journal Entries.
     */
    public array $journals = [];

    /**
     * @var stdClass Ledger attribute settings.
     */
    public stdClass $rules;

    /**
     * @var Section[] Section definitions.
     */
    public array $sections = [];

    /**
     * @var string A Chart of Accounts template to use.
     */
    public string $template;

    /**
     * @var string The path to the CoA template (read only).
     */
    protected string $templatePath;

    /**
     * @var Carbon The opening balance date.
     */
    public Carbon $transDate;

    /**
     * Property get.
     * @param $name
     * @return string|null
     */
    public function __get($name)
    {
        if ($name === 'templatePath') {
            return $this->templatePath;
        }
        return null;
    }

    private function extractAccounts(array $data): array
    {
        $errors = [];
        $this->accounts = [];
        foreach ($data['accounts'] ?? [] as $index => $accountData) {
            try {
                $message = Account::fromArray(
                    $accountData, self::OP_ADD| self::OP_CREATE
                );
                $this->accounts[$message->code] = $message;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Account', 'index' => $index + 1]
                );
            }
        }

        return $errors;
    }

    private function extractBalances(array $data): array
    {
        $errors = [];
        $this->balances = [];
        foreach ($data['balances'] ?? [] as $index => $balanceData) {
            try {
                $message = Balance::fromArray(
                    $balanceData, self::OP_ADD | self::OP_CREATE
                );
                $this->balances[] = $message;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Balance', 'index' => $index + 1]
                );
            }
        }

        return $errors;
    }

    private function extractCurrencies(array$data): array
    {
        $errors = [];
        $this->currencies = [];
        foreach ($data['currencies'] ?? [] as $index => $currency) {
            try {
                $message = Currency::fromArray($currency, self::OP_ADD | self::OP_CREATE);
                $this->currencies[$message->code] = $message;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index: " . $exception->getErrors(),
                    ['property' => 'Currency', 'index' => $index + 1]
                );
            }
        }

        return $errors;
    }

    private function extractDomains(array $data): array
    {
        $errors = [];
        $this->domains = [];
        foreach ($data['domains'] ?? [] as $index => $domain) {
            try {
                $domain = Domain::fromArray($domain, self::OP_ADD | self::OP_CREATE);
                $this->domains[] = $domain;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Domain', 'index' => $index + 1]
                );
            }
        }
        return $errors;
    }

    private function extractJournals(array $data): array
    {
        $errors = [];
        $this->journals = [];
        foreach ($data['journals'] ?? [] as $index => $journal) {
            try {
                $journal = SubJournal::fromArray(
                    $journal, self::OP_ADD | self::OP_CREATE
                );
                $this->journals[$journal->code] = $journal;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Journal', 'index' => $index + 1]
                );
            }
        }
        return $errors;
    }

    private function extractSections(array $data): array
    {
        $errors = [];
        $this->sections = [];
        foreach ($data['sections'] ?? [] as $index => $sectionData) {
            try {
                $this->sections[] = Section::fromArray($sectionData, ['checkAccount' =>false]);
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Section', 'index' => $index + 1]
                );
            }
        }
        return $errors;
    }

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_CREATE): self
    {
        $errors = [];
        $create = new Create();
        try {
            Merge::arrays($errors, $create->extractAccounts($data));
            Merge::arrays($errors, $create->extractBalances($data));
            Merge::arrays($errors, $create->loadNames($data, $opFlags));

            Merge::arrays($errors, $create->extractDomains($data));
            Merge::arrays($errors, $create->extractCurrencies($data));
            Merge::arrays($errors, $create->extractSections($data));
            if (count($create->currencies) === 0) {
                $errors[] = __('At least one currency is required.');
            }

            Merge::arrays($errors, $create->extractJournals($data));
            if ($data['template'] ?? false) {
                $create->template = $data['template'];
            }
            if (isset($data['transDate'])) {
                $create->transDate = new Carbon($data['transDate']);
            } elseif (isset($data['date'])) {
                $create->transDate = new Carbon($data['date']);
            }
            // Convert the array into a stdClass
            $ruleArray = $data['rules'] ?? (object)[];
            $create->rules = json_decode(json_encode($ruleArray));
        }
        catch (TypeError $exception) {
            if (
                preg_match(
                    '!Cannot assign (\S+) .*?\$(\S+) of type \??(\S+)!',
                    $exception->getMessage(),
                    $matches
                )
            ) {
                $errors[] = __(
                    'Property :prop should be :expect, not :actual.',
                    ['prop' => $matches[2], 'expect' => $matches[3], 'actual' => $matches[1]]
                );
            } else {
                $errors[] = $exception->getMessage();
            }
        }
        if (count($errors)) {
            // The request itself is not valid.
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $create;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $this->transDate ??= Carbon::now();
        if (isset($this->template)) {
            $this->templatePath = resource_path(
                "ledger/charts/$this->template.json"
            );
            $this->templatePath = Package::chartPath("$this->template.json");
            if (!file_exists($this->templatePath)) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST, [__('Specified template not found in ledger/charts.')]
                );
            }
        }
        foreach ($this->accounts as $account) {
            $account->validate($opFlags);
        }
        foreach ($this->currencies as $currency) {
            $currency->validate($opFlags);
        }
        if (count($this->domains) === 0) {
            // Create a default domain
            $this->domains[self::DEFAULT_DOMAIN] = Domain::fromArray(
                [
                    'code' => self::DEFAULT_DOMAIN,
                    'name' => 'Main General Ledger',
                    'language' => App::getLocale(),
                ],
                Message::OP_CREATE
            );

        }
        foreach ($this->journals as $journal) {
            $journal->validate($opFlags);
        }
        foreach ($this->names as $name) {
            $name->validate($opFlags);
        }

        return $this;
    }
}
