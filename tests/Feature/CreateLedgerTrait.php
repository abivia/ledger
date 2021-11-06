<?php
/** @noinspection PhpParamsInspection */

namespace Tests\Feature;

trait CreateLedgerTrait {
    protected array $createRequest = [
        'language' => 'en-CA',
        'domains' => [
            [
                'code' => 'GL',
                'names' => [
                    [
                        'name' => 'General Ledger',
                        'language' => 'en-CA'
                    ],
                    [
                        'name' => 'Grand Livre',
                        'language' => 'fr-CA'
                    ]
                ]
            ]
        ],
        'currencies' => [
            [
                'code' => 'CAD',
                'decimals' => 2
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
                'codeFormat' => '/[a-z0-9\-]+/i'
            ]
        ],
        'extra' => 'arbitrary JSON',
        'template' => 'sections'
    ];

    protected function createLedger(array $without = [])
    {
        $create = $this->createRequest;
        foreach ($without as $item) {
            unset($create[$item]);
        }
        $response = $this->postJson(
            'api/v1/ledger/create', $create
        );
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertFalse(isset($response['errors']));

        return $response;
    }

}
