<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class RootTest extends TestCase
{
    use CommonChecks;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'templates';
    }

    public function testBadOperation()
    {
        $response = $this->postJson(
            'api/ledger/root/bogus', []
        );
        $actual = $this->isFailure($response);
        $this->assertCount(1, $actual->errors);
    }

    public function testBadRequest()
    {
        $badJson = 'this is not valid JSON.';
        $headers = [
            'CONTENT_LENGTH' => mb_strlen($badJson, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ];
        $response = $this->call(
            'POST',
            'api/ledger/currency/query',
            [],
            $this->prepareCookiesForJsonRequest(),
            [],
            $this->transformHeadersToServerVars($headers),
            $badJson
        );
        $actual = $this->isFailure($response);
        $this->assertCount(2, $actual->errors);
    }

    public function testDebug()
    {
        // Ensure debugging is off
        session(['ledger.api_debug' => 0]);
        $response = $this->postJson(
            'api/ledger/root/bogus', []
        );
        $actual = $this->isFailure($response);
        $this->assertFalse(isset($actual->apiVersion));
        $this->assertFalse(isset($actual->version));
        // Turn debugging on
        session(['ledger.api_debug' => 1]);
        $response = $this->postJson(
            'api/ledger/root/bogus', []
        );
        $actual = $this->isFailure($response);
        $this->assertTrue(isset($actual->apiVersion));
        $this->assertTrue(isset($actual->version));
    }

    public function testListTemplates()
    {
        $response = $this->postJson(
            'api/ledger/root/templates', []
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->templates);
        $this->assertEquals('manufacturer_1.0', $actual->templates[0]->name);
        $this->assertEquals('sections', $actual->templates[1]->name);
    }

}
