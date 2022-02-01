<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Example;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Http\Controllers\ReportController;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\Balance;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\ReportAccount;
use Abivia\Ledger\Tests\TestCase;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function str_pad;
use function strlen;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class GettingStartedTest extends TestCaseWithMigrations
{
    use RefreshDatabase;

    /**
     * Create the ledger and record the initial investment as the opening transaction.
     *
     * @return void
     * @throws Breaker
     */
    private function stepCreateLedger()
    {
        $create = new Create();

        // Name the enterprise
        $create->names[] = Name::fromArray(['name' => 'Sells Stuff LLC']);

        // Define the ledger accounts
        $accounts = [
            ['code' => '110', 'debit' => true, 'name' => 'Bank Account'],
            ['code' => '115', 'debit' => true, 'name' => 'Payment Processor'],
            ['code' => '130', 'debit' => true, 'name' => 'Accounts Receivable'],
            ['code' => '140', 'debit' => true, 'name' => 'Inventory'],
            ['code' => '225', 'credit' => true, 'name' => 'Sales Tax Payable'],
            ['code' => '310', 'credit' => true, 'name' => 'Common Stock'],
            ['code' => '410', 'credit' => true, 'name' => 'Sales'],
            ['code' => '510', 'credit' => true, 'name' => 'Cost of Goods Sold'],
            ['code' => '692', 'debit' => true, 'name' => 'Transaction Fees'],
        ];
        foreach ($accounts as $account) {
            $create->accounts[] = Account::fromArray($account);
        }

        // Record the initial investment as the opening transaction.
        $create->balances[] = Balance::fromArray([
            'code' => '110',
            'amount' => '-500',
            'currency' => 'USD'
        ]);
        $create->balances[] = Balance::fromArray([
            'code' => '310',
            'amount' => '500',
            'currency' => 'USD'
        ]);

        // The business is founded January 1st, 2022
        $create->transDate = Carbon::create(2022, 1, 1);

        // Define US dollars (2 decimals)
        $create->currencies[] = Currency::fromArray(['code' => 'USD', 'decimals' => 2]);
        $accountController = new LedgerAccountController();
        $accountController->create($create);
    }

    /**
     * @throws Breaker
     */
    private function stepReport()
    {
        // At the end of the first week, the founders want a trial balance.
        $request = new Report();
        $request->name = 'trialBalance';
        $request->currency = 'USD';
        $request->toDate = new Carbon('2022-01-07');
        $report = new ReportController();
        $results = $report->generate($request);
        $maxName = 0;
        /** @var ReportAccount $result */
        foreach ($results['accounts'] as $result) {
            $maxName = max($maxName, strlen($result->name));
        }
        $maxName++;
        foreach ($results['accounts'] as $result) {
            echo $result->code . ' '
                . str_pad($result->name, $maxName, ' ', STR_PAD_RIGHT);
            if (substr($result->balance, 0, 1) === '-') {
                echo str_pad('(' . substr($result->balance, 1) . ')', 9, ' ', STR_PAD_LEFT);
            } else {
                echo str_pad($result->balance, 8, ' ', STR_PAD_LEFT);
            }
            echo "\n";
        }
    }

    /**
     * @throws Breaker
     */
    private function stepTransactions()
    {
        $entryController = new JournalEntryController();
        // The day after founding, purchase $150 worth of inventory
        $entryController->add(
            Entry::fromArray([
                'currency' => 'USD',
                'description' => 'Widget Wholesale Inc.',
                'details' => [
                    ['code' => '110', 'amount' => '150'],
                    ['code' => '140', 'amount' => '-150'],
                ],
                'extra' => '15 Widgets @ $10.00 each',
                'transDate' => '2022-01-02 09:00',
            ])
        );

        // Things are going well, we made a sale on the same day!
        // Sold one widget online at $20, with 10% tax. This is a split transaction.
        // The card processor collects $22, we record $20 as sales and $2 as sales tax.
        $entryController->add(
            Entry::fromArray([
                'currency' => 'USD',
                'description' => 'Widget sale customer #1',
                'details' => [
                    ['code' => '410', 'amount' => '20'],
                    ['code' => '225', 'amount' => '2'],
                    ['code' => '115', 'amount' => '-22'],
                ],
                'transDate' => '2022-01-02 11:15',
            ])
        );
        // We also reduce our inventory, moving the item to cost of sales
        $entryController->add(
            Entry::fromArray([
                'currency' => 'USD',
                'description' => 'Widget sale customer #1',
                'details' => [
                    ['code' => '140', 'amount' => '10'],
                    ['code' => '510', 'amount' => '-10'],
                ],
                'transDate' => '2022-01-02 11:15',
            ])
        );

        // A few days later, our card processor sends money to our bank, less $1.50 in fees.
        $entryController->add(
            Entry::fromArray([
                'currency' => 'USD',
                'description' => 'Deposit from card processor',
                'details' => [
                    ['code' => '115', 'amount' => '22'],
                    ['code' => '110', 'amount' => '-20.50'],
                    ['code' => '692', 'amount' => '-1.50'],
                ],
                'transDate' => '2022-01-06 04:00',
            ])
        );
    }

    /**
     * Run the "getting started" example.
     *
     * @return void
     * @throws Exception
     */
    public function testGettingStarted(): void
    {
        try {
            $this->stepCreateLedger();
            $this->stepTransactions();
            $this->stepReport();
        } catch (Breaker $e) {
            echo implode("\n", $e->getErrors(true)) . "\n";
            $trace = $e->getTrace();
            $from = $trace[0];
            echo $from['file'] . ' line ' . $from['line'];
        }

        $this->assertTrue(true);
    }

}
