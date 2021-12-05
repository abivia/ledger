<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

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
        self::$expectContent = 'templates';
    }

    public function testListTemplates()
    {
        $response = $this->postJson(
            'api/ledger/root/templates', []
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->templates);
        $this->assertEquals('manufacturer', $actual->templates[0]->name);
        $this->assertEquals('sections', $actual->templates[1]->name);
    }

}
