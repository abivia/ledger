<?php

namespace Abivia\Ledger\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Helpers\Package;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;
use TypeError;

class Create extends Message
{
    /**
     * @var Account[]
     */
    public array $accounts = [];

    /**
     * @var Balance[] Opening balances
     */
    public array $balances = [];
    /**
     * @var Currency[]
     */
    public array $currencies = [];
    /**
     * @var Domain[]
     */
    public array $domains = [];
    /**
     * @var SubJournal[]
     */
    public array $journals = [];
    /**
     * @var Name[]
     */
    public array $names = [];
    public array $rules = [];
    public string $template;
    public string $templatePath;
    public Carbon $transDate;

    private function extractAccounts(array $data): array
    {
        $errors = [];
        $this->accounts = [];
        foreach ($data['accounts'] ?? [] as $index => $accountData) {
            try {
                $message = Account::fromRequest(
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
                $message = Balance::fromRequest(
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
                $message = Currency::fromRequest($currency, self::OP_ADD | self::OP_CREATE);
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
                $domain = Domain::fromRequest($domain, self::OP_ADD | self::OP_CREATE);
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
                $journal = SubJournal::fromRequest(
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

    private function extractNames(array $data): array
    {
        $errors = [];
        $this->names = [];
        $nameList = $data['names'] ?? [];
        if (isset($data['name'])) {
            array_unshift($nameList, ['name' => $data['name']]);
        }
        foreach ($nameList as $index => $name) {
            try {
                $message = Name::fromRequest($name, self::OP_ADD | self::OP_CREATE);
                $this->names[$message->language] = $message;
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index :message.",
                    [
                        'property' => 'Name',
                        'index' => $index + 1,
                        'message' => $exception->getMessage()
                    ]
                );
            }
        }
        return $errors;
    }

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $errors = [];
        $create = new Create();
        try {
            Merge::arrays($errors, $create->extractAccounts($data));
            Merge::arrays($errors, $create->extractBalances($data));
            Merge::arrays($errors, $create->extractNames($data));

            Merge::arrays($errors, $create->extractDomains($data));
            Merge::arrays($errors, $create->extractCurrencies($data));
            if (count($create->currencies) === 0) {
                $errors[] = __('At least one currency is required.');
            }

            Merge::arrays($errors, $create->extractJournals($data));
            if ($data['template'] ?? false) {
                $create->template = $data['template'];
            }
            if (isset($data['date'])) {
                $create->transDate = new Carbon($data['date']);
            }
            $create->rules = $data['rules'] ?? [];
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
    public function validate(int $opFlags): self
    {
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
            $this->domains['MAIN'] = domain::fromRequest(
                [
                    'code' => 'MAIN',
                    'names' => [
                        'name' => 'Main General Ledger',
                        'language' => 'en'
                    ]
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
