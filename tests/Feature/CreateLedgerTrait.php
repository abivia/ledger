<?php
/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Tests\Feature;

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
            'account' => [
                'codeFormat' => '/^[a-z0-9\-]+$/i'
            ],
            'pageSize' => 25,
        ],
        'extra' => 'arbitrary JSON',
        'template' => 'sections'
    ];

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
            'api/v1/ledger/root/create', $create
        );
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertEquals($expectErrors, isset($response['errors']));

        return $response;
    }

}
