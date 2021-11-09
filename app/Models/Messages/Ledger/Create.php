<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class Create extends Message
{
    // TODO: add opening balance capability (account code, currency, balance)
    /**
     * @var Account[]
     */
    public array $accounts = [];
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
    public ?string $template = null;
    public ?string $templatePath = null;

    private function extractAccounts(array $data): array
    {
        $errors = [];
        $this->accounts = [];
        foreach ($data['accounts'] ?? [] as $index => $accountData) {
            try {
                $message = Account::fromRequest(
                    $accountData, self::OP_ADD | Message::OP_VALIDATE
                );
                $accounts[$message->code] = $message;
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

    private function extractCurrencies(array$data): array
    {
        $errors = [];
        $this->currencies = [];
        foreach ($data['currencies'] ?? [] as $index => $currency) {
            try {
                $message = Currency::fromRequest($currency, self::OP_ADD | Message::OP_VALIDATE);
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

    private function extractDomains(array $data, bool $makeDefault = false): array
    {
        $errors = [];
        $this->domains = [];
        $firstDomain = null;
        foreach ($data['domains'] ?? [] as $index => $domain) {
            try {
                $domain = Domain::fromRequest($domain, self::OP_ADD | Message::OP_VALIDATE);
                $this->domains[$domain->code] = $domain;
                if ($firstDomain === null) {
                    $firstDomain = $domain->code;
                }
            } catch (Breaker $exception) {
                $errors[] = __(
                    ":Property in position :index "
                    . implode(', ', $exception->getErrors()) . ".",
                    ['property' => 'Domain', 'index' => $index + 1]
                );
            }
        }
        if (count($this->domains) < 1 && $makeDefault) {
            // Create a default domain
            $firstDomain = 'GJ';
            $this->domains['GJ'] = [
                'code' => 'GJ',
                'names' => [
                    'name' => 'General Journal',
                    'language' => 'en'
                ]
            ];
        }
        if ($firstDomain !== null) {
            $defaultDomain = LedgerAccount::rules()->domain->default ?? null;
            $ruleUpdate = ['domain' => ['default' => $firstDomain]];
            if (
                $defaultDomain === null
                || !isset($this->domains[$defaultDomain])
            ) {
                LedgerAccount::bootRules($ruleUpdate);
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
                    $journal, self::OP_ADD | Message::OP_VALIDATE
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
        foreach ($data['names'] ?? [] as $index => $name) {
            try {
                $message = Name::fromRequest($name, self::OP_ADD | Message::OP_VALIDATE);
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

    public static function fromRequest(array $data, int $opFlag): self
    {
        $errors = [];
        // Set up the ledger boot rules object before loading anything.
        if (isset($data['rules'])) {
            // Recode and decode the rules as objects
            LedgerAccount::bootRules($data['rules']);
        }
        $create = new Create();
        Merge::arrays($errors, $create->extractAccounts($data));
        Merge::arrays($errors, $create->extractNames($data));

        Merge::arrays($errors, $create->extractDomains($data, true));
        Merge::arrays($errors, $create->extractCurrencies($data));
        if (count($create->currencies) === 0) {
            $errors[] = __('At least one currency is required.');
        }

        Merge::arrays($errors, $create->extractJournals($data));
        if ($data['template'] ?? false) {
            $create->template = $data['template'];
        } else {
            $create->template = null;
            $create->templatePath = null;
        }
        if (count($errors)) {
            // The request itself is not valid.
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $create;
    }

    public function validate(int $opFlag): self
    {
        if ($this->template !== null) {
            $this->templatePath = resource_path(
                "ledger/charts/{$this->template}.json"
            );
            if (!file_exists($this->templatePath)) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST, [__('Specified template not found in ledger/charts.')]
                );
            }
        }
        foreach ($this->accounts as $account) {
            $account->validate(self::OP_ADD);
        }
        foreach ($this->currencies as $currency) {
            $currency->validate(self::OP_ADD);
        }
        foreach ($this->journals as $journal) {
            $journal->validate(self::OP_ADD);
        }
        foreach ($this->names as $name) {
            $name->validate(self::OP_ADD);
        }

        return $this;
    }
}
