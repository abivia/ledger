<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\Messages\Ledger\Name;
use App\Models\Messages\Message;
use Tests\TestCase;


class NameTest extends TestCase
{

    public function testFromRequestAdd()
    {
        $name = Name::fromRequest(
            [
                'name' => 'In English', 'language' => 'en',
            ],
            Message::OP_ADD | Message::FN_VALIDATE
        );
        $this->assertEquals('en', $name->language);
        $this->assertEquals('In English', $name->name);
    }

    public function testFromRequestAdd_bad1()
    {
        $this->expectException(Breaker::class);
        Name::fromRequest(
            [
                'language' => 'en',
            ],
            Message::OP_ADD | Message::FN_VALIDATE
        );
    }

    public function testFromRequestAdd_default_language()
    {
        $name = Name::fromRequest(
            [
                'name' => 'In English',
            ],
            Message::OP_ADD | Message::FN_VALIDATE
        );
        $this->assertEquals('en', $name->language);
        $this->assertEquals('In English', $name->name);
    }

    public function testFromRequestListAdd()
    {
        $source = [
            ['name' => 'In English', 'language' => 'en'],
            ['name' => 'en francais', 'language' => 'fr'],
        ];
        $names = Name::fromRequestList(
            $source, Message::OP_ADD | Message::FN_VALIDATE, 1
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
                $source, Message::OP_ADD | Message::FN_VALIDATE, 3
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
