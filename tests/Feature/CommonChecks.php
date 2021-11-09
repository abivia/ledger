<?php
/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;

trait CommonChecks {

    protected static string $expectContent = '';

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
