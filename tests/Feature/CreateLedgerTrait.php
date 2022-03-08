<?php
/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Models\LedgerAccount;
use Carbon\Carbon;
use Exception;

trait CreateLedgerTrait {
    protected array $createRequest = [
        'language' => 'en-CA',
        'date' => '2021-01-01',
        'domains' => [
            [
                'code' => 'Corp',
                'names' => [
                    [
                        'name' => 'General Corporate',
                        'language' => 'en-CA'
                    ],
                    [
                        'name' => 'Général Corporatif',
                        'language' => 'fr-CA'
                    ]
                ]
            ]
        ],
        'currencies' => [
            [
                'code' => 'CAD',
                'decimals' => 2
            ],
            [
                'code' => 'ZZZ',
                'decimals' => 4
            ]
        ],
        'names' => [
            [
                'name' => 'General Ledger Test',
                'language' => 'en-CA'
            ],
            [
                'name' => 'Tester le grand livre',
                'language' => 'fr-CA'
            ]
        ],
        'rules' => [
//            'account' => [
//                'codeFormat' => '/^[a-z0-9\-]+$/i'
//            ],
            'pageSize' => 25,
            '_myAppRule' => [1, 2, 3],
        ],
        'extra' => 'arbitrary string',
        'template' => 'sections'
    ];

    /**
     * @throws Exception
     */
    protected function addRandomTransactions(int $count) {
        // Get a list of accounts in the ledger
        $codes = $this->getPostingAccounts();
        $forDate = new Carbon('2001-01-02');
        $transId = 0;
        $shuffled = [];
        shuffle($shuffled);
        $controller = new JournalEntryController();
        try {
            while ($transId++ < $count) {
                if (count($shuffled) < 2) {
                    $shuffled = $codes;
                    shuffle($shuffled);
                }
                $entry = new Entry();
                $entry->currency = 'CAD';
                $entry->description = "Random entry $transId";
                $entry->transDate = clone $forDate;
                $entry->transDate->addDays(random_int(0, $count));
                $amount = (float)random_int(-99999, 99999);
                $entry->details = [
                    new Detail(
                        new EntityRef(array_pop($shuffled)),
                        (string)($amount / 100)
                    ),
                    new Detail(
                        new EntityRef(array_pop($shuffled)),
                        (string)(-$amount / 100)
                    ),
                ];
                $controller->add($entry);
            }
        } catch (Breaker $exception) {
            echo $exception->getMessage() . "\n"
                . implode("\n", $exception->getErrors());
        }
    }

    protected function createLedger(
        array $without = [],
        array $with = [],
        bool $expectErrors = false
    ) {
        $create = $this->createRequest;
        foreach ($without as $item) {
            unset($create[$item]);
        }
        $create = array_merge_recursive($create, $with);
        $response = $this->postJson(
            'api/ledger/root/create', $create
        );
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertEquals(
            $expectErrors,
            isset($response['errors']),
            isset($response['errors'])
                ? implode("\n", $response['errors']) : ''
        );

        return $response;
    }

    protected function getPostingAccounts(): array
    {
        $codes = [];
        foreach (LedgerAccount::all() as $account) {
            // Get rid of the root and any category accounts
            if ($account->code != '' && !$account->category) {
                $codes[] = $account->code;
            }
        }
        return $codes;
    }

}
