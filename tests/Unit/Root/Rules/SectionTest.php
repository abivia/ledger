<?php

namespace Abivia\Ledger\Tests\Unit\Root\Rules;

use Abivia\Ledger\Root\Rules\Section;
use Abivia\Ledger\Tests\TestCase;

class SectionTest extends TestCase
{
    public static Section $section;

    public function testHydration()
    {
        $obj = new \Abivia\Ledger\Root\Rules\Section();
        $json = <<<JSON
{
    "codes": "1100",
    "credit": true,
    "name": "This is a section name"
}
JSON;

        $this->assertTrue(
            $obj->hydrate($json, ['checkAccount' => false])
        );
        $this->assertEquals(
            'This is a section name',
            $obj->names[0]->name
        );
        $this->assertTrue($obj->credit);
        self::$section = $obj;
    }

    public function testHydrationArray()
    {
        $obj = new \Abivia\Ledger\Root\Rules\Section();
        $json = <<<JSON
{
    "codes": ["1100", "1200", "1300"],
    "credit": true,
    "name": "This is a section name"
}
JSON;

        $this->assertTrue(
            $obj->hydrate($json, ['checkAccount' => false])
        );
        $this->assertEquals(
            'This is a section name',
            $obj->names[0]->name
        );
        $this->assertEquals(
            ['1100', '1200', '1300'],
            $obj->codes
        );
        $this->assertTrue($obj->credit);
    }

    /**
     * @depends testHydration
     * @return void
     */
    public function testEncode()
    {
        $json = json_encode(self::$section);
        $expect = '{"codes":["1100"],"credit":true,'
            . '"ledgerUuids":[],'
            . '"names":[{"language":"en","name":"This is a section name"}]}';
        $this->assertEquals($expect, $json);
    }

}
