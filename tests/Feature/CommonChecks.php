<?php
/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerAccount;
use Illuminate\Testing\TestResponse;

trait CommonChecks {

    protected static string $expectContent = '';

    protected function dumpLedger()
    {
        LedgerAccount::loadRoot();
        //print_r(LedgerAccount::root());
        foreach (LedgerAccount::all() as $item) {
            echo "$item->ledgerUuid $item->code ($item->parentUuid) ";
            echo $item->category ? 'cat ' : '    ';
            if ($item->debit) echo 'DR __';
            if ($item->credit) echo '__ CR';
            echo "\n";
            foreach ($item->names as $name) {
                echo "$name->name $name->language\n";
            }
        }
    }

    private function hasAttributes(array $attributes, object $object)
    {
        foreach ($attributes as $attribute) {
            $this->assertObjectHasAttribute($attribute, $object);
        }
    }

    private function hasRevisionElements(object $account)
    {
        $this->assertTrue(isset($account->revision));
        $this->assertTrue(isset($account->createdAt));
        $this->assertTrue(isset($account->updatedAt));
    }

    private function isFailure(TestResponse $response)
    {
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertTrue(isset($response['errors']));
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);
        $this->assertCount(2, (array)$actual);

        return $actual;
    }

    /**
     * Make sure the response was not an error and is well-structured.
     * @param TestResponse $response
     * @param string $expect
     * @return mixed Decoded JSON response
     */
    private function isSuccessful(
        TestResponse $response,
        ?string $expect = null
    )
    {
        $expectContent = $expect ?? self::$expectContent;
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertFalse(isset($response['errors']));
        if ($expectContent !== '') {
            $this->assertTrue(isset($response[$expectContent]));
        }
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);

        return $actual;
    }

}
