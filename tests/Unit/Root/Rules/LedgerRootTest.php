<?php

namespace Abivia\Ledger\Tests\Unit\Root\Rules;

use Abivia\Ledger\Root\Rules\LedgerRules;
use Abivia\Ledger\Tests\TestCase;

class LedgerRootTest extends TestCase
{
    public static LedgerRules $ledger;

    public string $subject = <<<JSON
{
    "account": {
        "postToCategory": true
    },
    "entry": {
        "reviewed": true
    },
    "sections": [
        {
            "codes": "1100",
            "credit": true,
            "name": "This is a section name"
        },
        {
            "codes": "2000",
            "credit": false,
            "name": "This is a second section name"
        }
    ],
    "openDate": "2021-12-23 15:46:49.126901",
    "pageSize": 50
}
JSON;

    public function testHydration()
    {
        $obj = new LedgerRules();

        $this->assertTrue(
            $obj->hydrate($this->subject, ['checkAccount' => false])
        );
        self::$ledger = $obj;
    }

    /**
     * @depends testHydration
     * @return void
     */
    public function testEncode()
    {
        $json = json_encode(self::$ledger);
        $expect = <<<JSON
{
    "account": {
        "postToCategory": true
    },
    "appAttributes": [],
    "batch":{
      "allowReports":true,
      "limit":0
    },
    "domain": {},
    "entry": {
        "reviewed": true
    },
    "language": {
        "default": "en"
    },
    "openDate": "2021-12-23 15:46:49.126901",
    "pageSize": 50,
    "sections": [
        {
            "codes": ["1100"],
            "credit": true,
            "ledgerUuids": [],
            "names": [
                {
                    "language": "en",
                    "name": "This is a section name"
                }
            ]
        },
        {
            "codes": ["2000"],
            "credit": false,
            "ledgerUuids": [],
            "names": [
                {
                    "language": "en",
                    "name": "This is a second section name"
                }
            ]
        }
    ]
}
JSON;
;
        $this->assertJsonStringEqualsJsonString($expect, $json);
    }

}
