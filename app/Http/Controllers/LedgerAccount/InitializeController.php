<?php
declare(strict_types=1);

namespace App\Http\Controllers\LedgerAccount;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerAccountController;
use App\Models\LedgerAccount;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Models\LedgerName;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Ledger\Create;
use App\Models\Messages\Ledger\EntityRef;
use App\Models\Messages\Message;
use App\Models\SubJournal;
use App\Traits\Audited;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;

class InitializeController extends LedgerAccountController
{
    use Audited;

    private array $currencies = [];
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
     *
     * @param Create $message
     * @return LedgerAccount
     * @throws Breaker
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

    private function createRoot(): string
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
        $rootUuid = LedgerAccount::root()->ledgerUuid;

        // Create the name records
        foreach ($this->initData->names as $name) {
            $name->ownerUuid = $rootUuid;
            LedgerName::createFromMessage($name);
        }

        return $rootUuid;
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
        $created = ['' => $this->createRoot()];

        // Create the accounts
        $emptyRun = false;
        while (!$emptyRun) {
            $emptyRun = true;
            foreach ($accounts as $code => $account) {
                $parentCode = $account->parent->code ?? null;
                $create = false;
                if ($parentCode !== null) {
                    if (isset($created[$parentCode])) {
                        $account->parent->uuid = $created[$parentCode];
                        $create = true;
                    }
                } else {
                    // No parent, always create
                    $account->parent = new EntityRef();
                    $account->parent->uuid = $created[''];
                    $create = true;
                }
                if ($create) {
                    /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                    $ledgerAccount = LedgerAccount::createFromMessage($account);
                    $created[$ledgerAccount->code] = $ledgerAccount->ledgerUuid;
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
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                [
                    __(
                        "Unable to create accounts :list because parent doesn't exist.",
                        ['list' => implode(', ', array_keys($accounts))]
                    )
                ]
            );

        }
    }

    private function initializeCurrencies(): void
    {
        $this->currencies = [];
        $this->firstCurrency = '';
        foreach ($this->initData->currencies as $currencyCode => $currency) {
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            LedgerCurrency::createFromMessage($currency);
            $this->currencies[$currencyCode] = $currencyCode;
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
        foreach ($this->initData->journals as $journalCode => $journal) {
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
                $message = Account::fromRequest($account, Message::OP_ADD);
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
