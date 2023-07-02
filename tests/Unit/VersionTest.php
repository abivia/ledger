<?php

namespace Abivia\Ledger\Tests\Unit;


use Abivia\Ledger\Helpers\Version;
use PHPUnit\Framework\TestCase;


class VersionTest extends TestCase
{
    /**
     * Make sure the API version matches the changelog
     * @return void
     */
    public function testVersion()
    {
        $fh = fopen(__DIR__ . '/../../CHANGELOG.md', 'r');
        while (1) {
            $line = fgets($fh);
            if ($line === false) {
                break;
            }
            if (str_starts_with($line, '## ')) {
                $changeVersion = trim(substr($line, 3));
                break;
            }
        }
        fclose($fh);
        $this->assertEquals(Version::core(), $changeVersion);
    }
}
