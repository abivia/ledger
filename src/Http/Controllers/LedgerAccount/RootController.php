<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\LedgerAccount;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Package;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\Balance;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Models\SubJournal;
use Abivia\Ledger\Root\Rules\Section;
use Abivia\Ledger\Traits\Audited;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;
use function pathinfo;

/**
 * Functions associated with creating and querying the Ledger root account.
 */
class RootController extends LedgerAccountController
{
    use Audited;

    /**
     * @var LedgerAccount[] List of the created accounts.
     */
    private array $accounts;

    /**
     * @var array Maps account codes to section index.
     */
    private array $codeToSection = [];

    /**
     * @var LedgerCurrency[] Supported currencies.
     */
    private array $currencies = [];

    /**
     * @var LedgerDomain[] List of the created domains.
     */
    private array $domains;

    /**
     * @var string The first currency referenced by the ledger. This will be used as a default
     * if a domain doesn't supply an alternate.
     */
    private string $firstCurrency;

    /**
     * @var Create|null Data associated with initial ledger create request.
     */
    private ?Create $initData = null;

    /**
     * @var Section[] List of section definitions.
     */
    private array $sections = [];

    /**
     * @var Account[]
     */
    private array $templateAccounts = [];

    private function buildSectionMap(int $key, Section $section, bool $overwrite)
    {
        foreach ($section->codes as $code) {
            if (!$overwrite && isset($this->codeToSection[$code])) {
                $this->errors[] = __(
                    "Account code :code cannot appear in multiple sections.",
                    ['code' => $code]
                );
                throw Breaker::withCode(Breaker::INVALID_DATA);
            }
            $this->codeToSection[$code] = $key;
        }
    }

    /**
     * Verify that the Ledger has not been created already.
     *
     * @throws Breaker
     */
    public static function checkNoLedgerExists()
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerAccount::count() !== 0) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION, [__('Ledger is not empty.')]
            );
        }
    }

    /**
     * Initialize a new Ledger.
     *
     * @param Create $message
     * @return LedgerAccount
     * @throws Breaker
     * @throws Exception
     */
    public function create(Create $message): LedgerAccount
    {
        try {
            $inTransaction = false;
            // The Ledger must be empty
            self::checkNoLedgerExists();

            // Set up the ledger boot rules object before anything else.
            LedgerAccount::resetRules();
            if (isset($message->rules)) {
                // Merge the rules into the defaults
                LedgerAccount::setRules($message->rules);
            }

            $message->validate(Message::OP_CREATE);
            $this->errors = [];

            $this->initData = $message;

            // We have a valid request, build the root and support structures
            DB::beginTransaction();
            $inTransaction = true;
            // Create currencies
            $this->initializeCurrencies();
            // Create domains
            $this->initializeDomains();
            // Create journals
            $this->initializeJournals();
            // Create accounts
            $this->initializeAccounts();
            // Validate and save sections
            $this->initializeSections();
            // Load initial balances
            $this->initializeBalances();
            // Commit everything
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return LedgerAccount::root();
    }

    /**
     * Create the root account.
     *
     * @throws Exception
     */
    private function createRoot(): LedgerAccount
    {
        $root = new LedgerAccount();
        $root->code = '';
        $root->parentUuid = null;
        $root->category = true;

        // Commit the boot rules and a revision salt into the flex property.
        $flex = new stdClass();
        $flex->rules = LedgerAccount::rules(true);
        $flex->rules->openDate = $this->initData->transDate->format(LedgerAccount::systemDateFormat());
        $flex->salt = bin2hex(random_bytes(16));
        $root->flex = $flex;
        $root->save();
        LedgerAccount::loadRoot();
        $root = LedgerAccount::root();
        $rootUuid = $root->ledgerUuid;

        // Create the name records
        foreach ($this->initData->names as $name) {
            $name->ownerUuid = $rootUuid;
            LedgerName::createFromMessage($name);
        }

        return $root;
    }

    /**
     * Merge any Chart of Accounts template with any accounts in the Create request, create
     * the accounts, and check for integrity.
     *
     * @throws Exception
     */
    private function initializeAccounts()
    {
        // Load any template.
        $this->loadTemplate();

        // Merge in accounts from the request
        foreach ($this->initData->accounts as $account) {
            $this->templateAccounts[$account->code] = $account;
        }

        // Create the ledger root account
        $this->accounts = ['' => $this->createRoot()];

        // Create the accounts
        $errors = [];
        $emptyRun = false;
        while (!$emptyRun) {
            $emptyRun = true;
            foreach ($this->templateAccounts as $code => $account) {
                $parentCode = $account->parent->code ?? null;
                $create = false;
                if ($parentCode === null) {
                    // No parent, always create
                    $account->parent = new EntityRef();
                    $account->parent->uuid = $this->accounts['']->ledgerUuid;
                    $create = true;
                } elseif (isset($this->accounts[$parentCode])) {
                    $parent = $this->accounts[$parentCode];
                    $account->parent->uuid = $parent->ledgerUuid;
                    $account->inheritFlagsFrom($parent);
                    $create = true;
                    if (($account->category ?? false) === true && $parent->category !== true) {
                        $errors[] = __(
                            "Account :code can't be a category because :parent is not a category.",
                            ['code' => $code, 'parent' => $parentCode]
                        );
                    }
                }
                if ($create) {
                    /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                    $ledgerAccount = LedgerAccount::createFromMessage($account);
                    $this->accounts[$ledgerAccount->code] = $ledgerAccount;
                    // Create the name records
                    foreach ($account->names as $name) {
                        // Revalidate to fill in any missing language code.
                        $name->validate();
                        $name->ownerUuid = $ledgerAccount->ledgerUuid;
                        LedgerName::createFromMessage($name);
                    }
                    unset($this->templateAccounts[$code]);
                    $emptyRun = false;
                }
            }
        }
        if (count($this->templateAccounts)) {
            $errors[] = __(
                "Unable to create accounts :list because parent doesn't exist.",
                ['list' => implode(', ', array_keys($this->templateAccounts))]
            );
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
    }

    /**
     * Generate a compound journal entry for the Ledger's opening balances.
     *
     * @throws Breaker
     */
    private function initializeBalances()
    {
        // Start by making sure all the currencies balance
        $currencyTotals = array_fill_keys(array_keys($this->currencies), '0');
        $totals = array_fill_keys(array_keys($this->domains), $currencyTotals);
        $byDomain = array_fill_keys(
            array_keys($this->domains),
            array_fill_keys(array_keys($this->currencies), [])
        );

        foreach ($this->initData->balances as $balance) {
            $balance->validate(Message::OP_CREATE);
            if (!isset($this->currencies[$balance->currency])) {
                throw Breaker::withCode(
                    Breaker::INVALID_DATA,
                    __(
                        'Balance for account :code has unknown currency :currency.',
                        ['code' => $balance->account->code, 'currency' => $balance->currency]
                    )
                );
            }
            $byDomain[$balance->domain->code][$balance->currency][] = $balance;
            $balance->addAmountTo(
                $totals[$balance->domain->code][$balance->currency],
                $this->currencies[$balance->currency]->decimals
            );
        }
        $errors = [];
        foreach ($totals as $domainCode => $byCurrency) {
            foreach ($byCurrency as $currencyCode => $total) {
                if (bccomp('0', $total, $this->currencies[$currencyCode]->decimals) !== 0) {
                    $errors[] = __(
                        'Opening balance mismatch in :domainCode for currency :currencyCode',
                        compact('domainCode', 'currencyCode')
                    );
                }
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::INVALID_DATA, $errors);
        }
        $errors = [];
        // Now generate an opening balance journal entry for each currency.
        $language = LedgerAccount::rules()->language->default;
        $transDate = $this->initData->transDate ?? new Carbon();
        foreach ($byDomain as $domainCode => $byCurrency) {
            $ledgerDomain = $this->domains[$domainCode];
            /**
             * @var string $currencyCode
             * @var Balance[] $balances
             */
            foreach ($byCurrency as $currencyCode => $balances) {
                if (count($balances) === 0) {
                    continue;
                }
                $decimals = $this->currencies[$currencyCode]->decimals;
                // The opening balance is special as it can have any number of debits and credits.
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                $journalEntry = JournalEntry::create([
                    'currency'=> $currencyCode,
                    'description'=> 'Opening Balance',
                    'domainUuid'=> $ledgerDomain->domainUuid,
                    'arguments'=> [],
                    'language'=> $language,
                    'opening'=> true,
                    'reviewed'=> true,
                    'transDate'=> $transDate,
                ]);

                foreach ($balances as $detail) {
                    $ledgerAccount = $this->accounts[$detail->account->code] ?? null;
                    if ($ledgerAccount === null) {
                        $errors[] = __(
                            "Account :code is not defined.",
                            ['code' => $detail->account->code]
                        );
                        continue;
                    }
                    if (!($ledgerAccount->debit || $ledgerAccount->credit)) {
                        $errors[] = __(
                            "Account :code can't be posted to.",
                            ['code' => $detail->account->code]
                        );
                        continue;
                    }
                    $journalDetail = new JournalDetail();
                    $journalDetail->journalEntryId = $journalEntry->journalEntryId;
                    $journalDetail->ledgerUuid = $ledgerAccount->ledgerUuid;
                    $journalDetail->amount = bcadd('0', $detail->amount, $decimals);
                    $journalDetail->save();
                    // Create the ledger balances
                    $ledgerBalance = new LedgerBalance();
                    $ledgerBalance->ledgerUuid = $journalDetail->ledgerUuid;
                    $ledgerBalance->domainUuid = $ledgerDomain->domainUuid;
                    $ledgerBalance->currency = $detail->currency;
                    $ledgerBalance->balance = bcadd('0', $detail->amount, $decimals);
                    $ledgerBalance->save();
                }
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
    }

    /**
     * Create the Ledger's currencies.
     *
     * @return void
     */
    private function initializeCurrencies(): void
    {
        $this->currencies = [];
        $this->firstCurrency = '';
        foreach ($this->initData->currencies as $currency) {
            $currencyCode = $currency->code;
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $this->currencies[$currencyCode] = LedgerCurrency::createFromMessage($currency);
            if ($this->firstCurrency === '') {
                $this->firstCurrency = $currencyCode;
            }
        }
    }

    /**
     * Create the Ledger's domains. If no domains are specified, create the default domain.
     *
     * @throws Breaker
     */
    private function initializeDomains(): void
    {
        $this->domains = [];
        foreach ($this->initData->domains as $domain) {
            $domain->validate(Message::OP_ADD | Message::OP_CREATE);
            $domainCode = $domain->code;
            $domain->currencyDefault = $domain->currencyDefault ?? $this->firstCurrency;
            if (!($this->currencies[$domain->currencyDefault] ?? false)) {
                $this->errors[] = __(
                    "Domain :domain has an undefined currency :currency.",
                    ['domain' => $domainCode, 'currency' => $domain->currencyDefault]
                );
                throw Breaker::withCode(Breaker::BAD_REQUEST);
            }
            $ledgerDomain = LedgerDomain::createFromMessage($domain);
            $this->domains[$domainCode] = $ledgerDomain;
            $ledgerUuid = $ledgerDomain->domainUuid;
            // Create the name records
            foreach ($domain->names as $name) {
                $name->ownerUuid = $ledgerUuid;
                LedgerName::createFromMessage($name);
            }
        }
        // Validate or set the default domain
        $defaultDomain = LedgerAccount::rules(required: false)->domain->default ?? null;
        if ($defaultDomain !== null) {
            if (!isset($this->domains[$defaultDomain])) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST,
                    [__(
                        'Default domain :domain is not defined.',
                        ['domain' => $defaultDomain]
                    )]
                );
            }
        } else {
            $ruleUpdate = (object) [
                'domain' => (object) [
                    'default' => array_key_first($this->domains)
                ]
            ];
            LedgerAccount::setRules($ruleUpdate);
        }
    }

    /**
     * Create the Ledger's sub-journals.
     *
     * @return void
     */
    private function initializeJournals()
    {
        foreach ($this->initData->journals as $journal) {
            $ledgerJournal = SubJournal::createFromMessage($journal);
            $ledgerUuid = $ledgerJournal->subJournalUuid;
            // Create the name records
            foreach ($journal['names'] as $name) {
                $name->ownerUuid = $ledgerUuid;
                LedgerName::createFromMessage($name);
            }
        }
    }

    /**
     * Validate, reorganize and save section settings.
     *
     * @return void
     * @throws Breaker
     */
    private function initializeSections()
    {
        // Merge sections from the ledger create message into any from the template.
        foreach ($this->initData->sections as $section) {
            $this->sections[] = $section;
            $key = array_key_last($this->sections);
            $this->buildSectionMap($key, $section, true);
        }
        $newSections = [];
        $sectionMap = [];

        // Rebuild the code lists
        ksort($this->codeToSection);
        foreach ($this->codeToSection as $code => $key) {
            if (!isset($sectionMap[$key])) {
                $this->sections[$key]->codes = [];
                $newSections[] = $this->sections[$key];
                $sectionMap[$key] = array_key_last($newSections);
            }
            $newKey = $sectionMap[$key];
            $newSections[$newKey]->codes[] = (string) $code;
        }

        $this->sections = $newSections;

        // Store the sections into the root
        $sectionsOnly = new stdClass();
        $sectionsOnly->sections = $newSections;
        LedgerAccount::setRules($sectionsOnly);
    }

    /**
     * Get a list of the names and title of the predefined templates.
     *
     * @return array
     */
    public static function listTemplates(): array
    {
        $list = [];
        $chartDir = Package::chartPath();
        foreach (scandir($chartDir) as $file) {
            $parts = pathinfo($file);
            if ($parts['extension'] === 'json') {
                $chart = @json_decode(file_get_contents("$chartDir/$file"));
                if ($chart !== null) {
                    $list[$parts['filename']] = [
                        'name' => $parts['filename'],
                        'title' => $chart->title,
                    ];
                }
            }
        }

        return $list;
    }

    /**
     * Load a Chart of Accounts template.
     *
     * @throws Breaker
     */
    private function loadTemplate()
    {
        $this->templateAccounts = [];
        if (isset($this->initData->template)) {
            $template = json_decode(
                file_get_contents($this->initData->templatePath), true
            );
            if ($template === false) {
                $this->errors[] = __(
                    "Template :template is not valid JSON.",
                    ['template' => $this->initData->template]
                );
                throw Breaker::withCode(Breaker::INVALID_DATA);
            }
            // If no account format has been set, see if we can inherit from the template
            if (
                !isset($this->initData->rules->account->codeFormat)
                && isset($template['codeFormat'])
            ) {
                $ruleUpdate = (object) [
                    'account' => (object) [
                        'codeFormat' => $template['codeFormat']
                    ]
                ];
                LedgerAccount::setRules($ruleUpdate);
            }
            foreach ($template['accounts'] as $account) {
                try {
                    // Pass OP_CREATE because we're in the boot process
                    $message = Account::fromArray(
                        $account, Message::OP_ADD | Message::OP_CREATE | Message::F_VALIDATE
                    );
                } catch (Breaker $exception) {
                    $errors = $exception->getErrors();
                    $errors[] = __(
                        "Template :template is badly structured.",
                        ['template' => $this->initData->template]
                    );
                    throw Breaker::withCode(Breaker::INVALID_DATA, $errors);
                }
                $this->templateAccounts[$message->code] = $message;
            }
            $this->codeToSection = [];
            $this->sections = [];
            /**
             * @var int $key
             * @var array $section */
            foreach ($template['sections'] ?? [] as $key => $section) {
                $this->sections[$key] = Section::fromArray($section, ['checkAccount' =>false]);
                $this->buildSectionMap($key, $this->sections[$key], false);
            }
        }
    }

}
