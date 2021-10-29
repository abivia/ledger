<?php
declare(strict_types=1);

namespace App\Http\Controllers\LedgerAccount;

use App\Exceptions\Breaker;
use App\Helpers\Revision;
use App\Http\Controllers\LedgerAccountController;
use App\Http\Controllers\LedgerCurrencyController;
use App\Http\Controllers\LedgerDomainController;
use App\Http\Controllers\LedgerNameController;
use App\Http\Controllers\SubJournalController;
use App\Models\LedgerAccount;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Models\LedgerName;
use App\Models\SubJournal;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

class InitializeController extends LedgerAccountController
{
    /**
     * @var stdClass|null Data associated with initial ledger creation.
     */
    private ?stdClass $initData = null;

    /**
     * @throws Breaker
     */
    private function checkNoLedgerExists()
    {
        if (LedgerAccount::count() !== 0) {
            $this->errors[] = __('Ledger is not empty.');
            throw Breaker::fromCode(Breaker::INVALID_OPERATION);
        }
    }

    private function extractAccounts(Request $request): array
    {
        $accounts = [];
        if ($request->has('accounts')) {
            foreach ($request->input('accounts') as $index => $account) {
                [$success, $parsed] = self::parseCreateRequest($account, $this->rules);
                if ($success) {
                    $accounts[$parsed['code']] = $parsed;
                } else {
                    $this->errors[] = __(
                        ":Property in position :index "
                        . implode(', ', $parsed) . ".",
                        ['property' => 'Account', 'index' => $index + 1]
                    );
                }
            }
        }
        return $accounts;
    }

    private function extractCurrencies(Request $request): array
    {
        $currencies = [];
        if ($request->has('currencies')) {
            foreach ($request->input('currencies') as $index => $currency) {
                [$success, $parsed] = LedgerCurrencyController::parseRequest($currency);
                if ($success) {
                    $currencies[$parsed['code']] = $parsed;
                } else {
                    $this->errors[] = __(
                        ":Property in position :index "
                        . implode(', ', $parsed) . ".",
                        ['property' => 'Currency', 'index' => $index + 1]
                    );
                }
            }
        }
        return $currencies;
    }

    private function extractDomains(Request $request, bool $makeDefault = false): array
    {
        $domains = [];
        $firstDomain = false;
        if ($request->has('domains')) {
            foreach ($request->input('domains') as $index => $domain) {
                [$success, $parsed] = LedgerDomainController::parseRequest($domain);
                if ($success) {
                    $domains[$parsed['code']] = $parsed;
                    if ($firstDomain === false) {
                        $firstDomain = $parsed['code'];
                    }
                } else {
                    $this->errors[] = __(
                        ":Property in position :index "
                        . implode(', ', $parsed) . ".",
                        ['property' => 'Domain', 'index' => $index + 1]
                    );
                }
            }
        }
        if (count($domains) < 1 && $makeDefault) {
            // Create a default domain
            $firstDomain = 'GJ';
            $domains[] = [
                'code' => $firstDomain,
                'names' => [
                    'name' => 'General Journal',
                    'language' => 'en'
                ]
            ];
        }
        $this->rules->domain ??= new stdClass();
        $this->rules->domain->default ??= 'GJ';
        return $domains;
    }

    private function extractJournals(Request $request): array
    {
        $journals = [];
        if ($request->has('journals')) {
            foreach ($request->input('journals') as $index => $journal) {
                [$success, $parsed] = SubJournalController::parseRequest($journal);
                if ($success) {
                    $journals[$parsed['code']] = $parsed;
                } else {
                    $this->errors[] = __(
                        ":Property in position :index "
                        . implode(', ', $parsed) . ".",
                        ['property' => 'Journal', 'index' => $index + 1]
                    );
                }
            }
        }
        return $journals;
    }

    private function extractNames(Request $request): array
    {
        $names = [];
        if ($request->has('names')) {
            foreach ($request->input('names') as $index => $name) {
                [$success, $parsed] = LedgerNameController::parseRequest($name);
                if ($success) {
                    $names[$parsed['language']] = $parsed;
                } else {
                    $this->errors[] = __(
                        ":Property in position :index "
                        . implode(', ', $parsed) . ".",
                        ['property' => 'Name', 'index' => $index + 1]
                    );
                }
            }
        }
        return $names;
    }

    /**
     * @throws Exception
     */
    private function initializeAccounts()
    {
        $accounts = [];
        if ($this->initData->template) {
            $template = json_decode(
                file_get_contents($this->initData->templatePath), true
            );
            if ($template === false) {
                $this->errors[] = __(
                    "Template :template is not valid JSON.",
                    ['template' => $this->initData->template]
                );
                throw Breaker::fromCode(Breaker::INVALID_DATA);
            }
            foreach ($template['accounts'] as $account) {
                [$success, $parsed] = self::parseCreateRequest($account, $this->rules);
                if (!$success) {
                    // This is an internal error.
                    foreach ($parsed as $message) {
                        $this->errors[] = __($message);
                    }
                    $this->errors[] = __(
                        "Template :template is badly structured.",
                        ['template' => $this->initData->template]
                    );
                    throw Breaker::fromCode(Breaker::INVALID_DATA);
                }
                $accounts[$parsed['code']] = $parsed;
            }
        }
        // Merge in accounts from the request
        foreach ($this->initData->accounts as $account) {
            $accounts[$account['code']] = $account;
        }

        // Create the ledger root account
        $root = new LedgerAccount();
        $root->code = '';
        $root->parentUuid = null;
        $root->category = true;

        $flex = new stdClass();
        $flex->rules = $this->rules;
        $flex->salt = bin2hex(random_bytes(16));
        $root->flex = $flex;
        $root->save();
        LedgerAccount::loadRoot();

        // Create the name records
        foreach ($this->initData->ledgerNames as $name) {
            $name['ownerUuid'] = $root->ledgerUuid;
            LedgerName::create($name);
        }
        $created = ['' => $root->ledgerUuid];

        // Create the accounts
        $emptyRun = false;
        while (!$emptyRun) {
            $emptyRun = true;
            foreach ($accounts as $code => $account) {
                $parent = $account['parent']['code'] ?? false;
                $create = false;
                if ($parent) {
                    if (isset($created[$parent])) {
                        $account['parentUuid'] = $created[$parent];
                        $create = true;
                    }
                } else {
                    // No parent, always create
                    $account['parentUuid'] = $created[''];
                    $create = true;
                }
                if ($create) {
                    $ledgerAccount = LedgerAccount::create($account);
                    $created[$ledgerAccount->code] = $ledgerAccount->ledgerUuid;
                    // Create the name records
                    foreach ($account['names'] as $name) {
                        $name['ownerUuid'] = $ledgerAccount->ledgerUuid;
                        LedgerName::create($name);
                    }
                    unset($accounts[$code]);
                    $emptyRun = false;
                }
            }
        }
        if (count($accounts)) {
            $this->errors[] = __(
                "Unable to create accounts :list because parent doesn't exist.",
                ['list' => implode(', ', array_keys($accounts))]
            );
            throw Breaker::fromCode(Breaker::BAD_REQUEST);

        }
    }

    private function initializeCurrencies(): array
    {
        $ledgerCurrencies = [];
        $this->initData->firstCurrency = '';
        foreach ($this->initData->currencies as $currencyCode => $currency) {
            $ledgerCurrencies[$currencyCode] = LedgerCurrency::create($currency);
            if ($this->initData->firstCurrency === '') {
                $this->initData->firstCurrency = $currencyCode;
            }
        }

        return $ledgerCurrencies;
    }

    /**
     * @throws Breaker
     */
    private function initializeDomains(): array
    {
        $ledgerDomains = [];
        foreach ($this->initData->domains as $domainCode => $domain) {
            $domain['currencyDefault'] ??= $this->initData->firstCurrency;
            if (!($this->initData->ledgerCurrencies[$domain['currencyDefault']] ?? false)) {
                $this->errors[] = __(
                    "Domain :domain has an undefined currency :currency.",
                    ['domain' => $domainCode, 'currency' => $domain['currencyDefault']]
                );
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }
            $ledgerDomain = new LedgerDomain();
            $ledgerDomain->code = $domainCode;
            $ledgerDomain->currencyDefault = $domain['currencyDefault'];
            $ledgerDomain->subJournals = $domain['subJournals'];
            $ledgerDomain->save();
            $ledgerDomains[$domainCode] = $ledgerDomain;
            $ledgerUuid = $ledgerDomain->domainUuid;
            // Create the name records
            foreach ($domain['names'] as $name) {
                $name['ownerUuid'] = $ledgerUuid;
                LedgerName::create($name);
            }
        }

        return $ledgerDomains;
    }

    private function initializeJournals(): array
    {
        $ledgerJournals = [];
        foreach ($this->initData->journals as $journalCode => $journal) {
            $ledgerJournal = new SubJournal();
            $ledgerJournal->code = $journalCode;
            $ledgerJournal->save();
            $ledgerJournals[$journalCode] = $ledgerJournal;
            $ledgerUuid = $ledgerJournal->subJournalUuid;
            // Create the name records
            foreach ($journal['names'] as $name) {
                $name['ownerUuid'] = $ledgerUuid;
                LedgerName::create($name);
            }
        }

        return $ledgerJournals;
    }

    /**
     * Initialize a new Ledger
     *
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function run(Request $request): array
    {
        $response = ['time' => new Carbon()];
        $this->errors = [];
        $inTransaction = false;
        try {
            $inTransaction = false;
            $this->initData = new stdClass();
            // The Ledger must be empty
            $this->checkNoLedgerExists();
            // Get any rules before extracting accounts
            if ($request->has('rules')) {
                // Recode and decode the rules as a class
                $this->rules = json_decode(json_encode($request->input('rules')));
            } else {
                $this->rules = new stdClass();
            }
            $this->initData->accounts = $this->extractAccounts($request);
            $this->initData->ledgerNames = $this->extractNames($request);

            $this->initData->domains = $this->extractDomains($request, true);
            $this->initData->currencies = $this->extractCurrencies($request);
            if (count($this->initData->currencies) === 0) {
                $this->errors[] = __('At least one currency is required.');
            }

            $this->initData->journals = $this->extractJournals($request);
            if ($request->has('template')) {
                $this->initData->template = $request->input('template');
                $this->initData->templatePath = resource_path(
                    "ledger/charts/{$this->initData->template}.json"
                );
            } else {
                $this->initData->template = false;
                $this->initData->templatePath = false;
            }
            if (
                $this->initData->template
                && !file_exists($this->initData->templatePath)
            ) {
                $this->errors[] = __('Specified template not found in ledger/charts.');
            }
            if (count($this->errors)) {
                // The request itself is not valid.
                throw Breaker::fromCode(Breaker::BAD_REQUEST);
            }

            // We have a valid request, build the root and support structures
            DB::beginTransaction();
            $inTransaction = true;

            // Create currencies
            $this->initData->ledgerCurrencies = $this->initializeCurrencies();

            // Create domains
            $this->initData->ledgerDomains = $this->initializeDomains();

            // Create journals
            $this->initData->ledgerJournals = $this->initializeJournals();

            // Create accounts
            $this->initializeAccounts();

            // Commit everything
            DB::commit();
            $inTransaction = false;
            // Add the ledger information to the response
            $response['ledger'] = [
                'uuid' => LedgerAccount::root()->ledgerUuid,
                'revision' => Revision::create(
                    LedgerAccount::root()->revision, LedgerAccount::root()->updated_at
                ),
                'createdAt' => LedgerAccount::root()->created_at,
                'updatedAt' => LedgerAccount::root()->updated_at,
            ];
            $this->success($request);
        } catch (Breaker $exception) {
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }

        if ($inTransaction) {
            DB::rollBack();
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return $response;
    }

}
