<?php
declare(strict_types=1);

namespace App\Http\Controllers\LedgerAccount;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerAccountController;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerBalance;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Models\LedgerName;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Ledger\Balance;
use App\Models\Messages\Ledger\Create;
use App\Models\Messages\Ledger\EntityRef;
use App\Models\Messages\Message;
use App\Models\SubJournal;
use App\Traits\Audited;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;

class InitializeController extends LedgerAccountController
{
    use Audited;

    /**
     * @var LedgerAccount[]
     */
    private array $accounts;
    /**
     * @var LedgerCurrency[]
     */
    private array $currencies = [];
    /**
     * @var LedgerDomain[]
     */
    private array $domains;

    private string $firstCurrency;
    /**
     * @var Create|null Data associated with initial ledger creation.
     */
    private ?Create $initData = null;

    /**
     * @throws Breaker
     */
    public static function checkNoLedgerExists()
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerAccount::count() !== 0) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION, [__('Ledger is not empty.')]
            );
        }
    }

    /**
     * Initialize a new Ledger
     * TODO: Add initial balance creation.
     *
     * @param Create $message
     * @return LedgerAccount
     * @throws Breaker
     * @throws Exception
     */
    public function create(Create $message): LedgerAccount
    {
        $message->validate(Message::OP_CREATE);
        $this->errors = [];
        $inTransaction = false;
        try {
            // The Ledger must be empty
            self::checkNoLedgerExists();

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
     * @throws Exception
     */
    private function createRoot(): LedgerAccount
    {
        $root = new LedgerAccount();
        $root->code = '';
        $root->parentUuid = null;
        $root->category = true;

        // Save the rules and a revision salt into the flex property.
        $flex = new stdClass();
        $flex->rules = LedgerAccount::rules();
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
     * @throws Exception
     */
    private function initializeAccounts()
    {
        // Load the template if provided.
        $accounts = $this->initData->template ? $this->loadTemplate() : [];

        // Merge in accounts from the request
        foreach ($this->initData->accounts as $account) {
            $accounts[$account->code] = $account;
        }

        // Create the ledger root account
        $this->accounts = ['' => $this->createRoot()];

        // Create the accounts
        $errors = [];
        $emptyRun = false;
        while (!$emptyRun) {
            $emptyRun = true;
            foreach ($accounts as $code => $account) {
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
                    $create = true;
                    if ($account->category === true && $parent->category !== true) {
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
                        $name->ownerUuid = $ledgerAccount->ledgerUuid;
                        LedgerName::createFromMessage($name);
                    }
                    unset($accounts[$code]);
                    $emptyRun = false;
                }
            }
        }
        if (count($accounts)) {
            $errors[] = __(
                "Unable to create accounts :list because parent doesn't exist.",
                ['list' => implode(', ', array_keys($accounts))]
            );
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
    }

    /**
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

        /** @var Balance $balance */
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
            $byDomain[$balance->domain][$balance->currency][] = $balance;
            $balance->addAmountTo(
                $totals[$balance->domain][$balance->currency],
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
                // The opening balance is special as it can have any number of debits and credits.
                $journalEntry = new JournalEntry();
                $journalEntry->currency = $currencyCode;
                $journalEntry->description = 'Opening Balance';
                $journalEntry->domainUuid = $ledgerDomain->domainUuid;
                $journalEntry->arguments = [];
                $journalEntry->language = $language;
                $journalEntry->opening = true;
                $journalEntry->posted = true;
                $journalEntry->reviewed = true;
                $journalEntry->transDate = $transDate;
                $journalEntry->save();
                $journalEntry->refresh();

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
                    $journalDetail->amount = $detail->amount;
                    $journalDetail->save();
                    // Create the ledger balances
                    $ledgerBalance = new LedgerBalance();
                    $ledgerBalance->ledgerUuid = $journalDetail->ledgerUuid;
                    $ledgerBalance->domainUuid = $ledgerDomain->domainUuid;
                    $ledgerBalance->currency = $detail->currency;
                    $ledgerBalance->balance = $detail->amount;
                    $ledgerBalance->save();
                }
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::INVALID_DATA, $errors);
        }
    }

    private function initializeCurrencies(): void
    {
        $this->currencies = [];
        $this->firstCurrency = '';
        foreach ($this->initData->currencies as $currencyCode => $currency) {
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $this->currencies[$currencyCode] = LedgerCurrency::createFromMessage($currency);
            if ($this->firstCurrency === '') {
                $this->firstCurrency = $currencyCode;
            }
        }
    }

    /**
     * @throws Breaker
     */
    private function initializeDomains(): void
    {
        $this->domains = [];
        foreach ($this->initData->domains as $domainCode => $domain) {
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
    }

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
     * @return array
     * @throws Breaker
     */
    private function loadTemplate(): array
    {
        $accounts = [];
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
        foreach ($template['accounts'] as $account) {
            try {
                $message = Account::fromRequest(
                    $account, Message::OP_ADD | Message::FN_VALIDATE
                );
            } catch (Breaker $exception) {
                $errors = $exception->getErrors();
                $errors[] = __(
                    "Template :template is badly structured.",
                    ['template' => $this->initData->template]
                );
                throw Breaker::withCode(Breaker::INVALID_DATA, $errors);
            }
            $accounts[$message->code] = $message;
        }

        return $accounts;
    }

}
