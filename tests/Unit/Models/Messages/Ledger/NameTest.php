<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class NameTest extends TestCase
{

    public function testFromRequestAdd()
    {
        $name = Name::fromArray(
            [
                'name' => 'In English', 'language' => 'en',
            ],
            Message::OP_ADD
        );
        $this->assertEquals('en', $name->language);
        $this->assertEquals('In English', $name->name);
    }

    public function testFromRequestAdd_bad1()
    {
        $this->expectException(Breaker::class);
        Name::fromArray(
            [
                'language' => 'en',
            ],
            Message::OP_ADD | Message::F_VALIDATE
        );
    }

    public function testFromRequestAdd_default_language()
    {
        $name = Name::fromArray(
            [
                'name' => 'In English',
            ],
            Message::OP_ADD
        );
        $this->assertFalse(isset($name->language));
        $this->assertEquals('In English', $name->name);
    }

    public function testFromRequestListAdd()
    {
        $source = [
            ['name' => 'In English', 'language' => 'en'],
            ['name' => 'en francais', 'language' => 'fr'],
        ];
        $names = Name::fromRequestList(
            $source, Message::OP_ADD, 1
        );
        $this->assertCount(2, $names);
        foreach ($source as $name) {
            $lang = $name['language'];
            $this->assertArrayHasKey($lang, $names);
            $this->assertEquals($lang, $names[$lang]->language);
            $this->assertEquals($name['name'], $names[$lang]->name);
        }
        try {
            Name::fromRequestList(
                $source, Message::OP_ADD, 3
            );
            $this->fail('Did not see expected exception.');
        } catch (Breaker $exception) {
            $errors = $exception->getErrors();
            $this->assertCount(1, $errors);
            $this->assertStringContainsString(
                'must provide at least 3',
                $errors[0]
            );
        }
    }

}
