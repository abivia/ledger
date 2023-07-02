<?php
/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerName;
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

    private function exportNullable($value): string
    {
        return ($value ? "'$value'" : "NULL");
    }

    protected function exportSnapshot(string $toPath)
    {
        // Stops PHPStorm from complaining about SQL syntax.
        $sqlBuster = 'INSERT INTO' . ' ';
        $lines = [];
        $lines[] = $sqlBuster . '`journal_details` (`journalDetailId`, `journalEntryId`, `ledgerUuid`, `amount`, `journalReferenceUuid`) VALUES ';
        $glue = '';
        foreach (JournalDetail::all() as $detail) {
            $lines[] = $glue . '('
                . $detail->journalDetailId
                . ', ' . $detail->journalEntryId
                . ", '$detail->ledgerUuid'"
                . ", '$detail->amount'"
                . ', ' . $this->exportNullable($detail->journalReferenceUuid)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($detail);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`journal_entries` (`journalEntryId`, `transDate`, `domainUuid`, `subJournalUuid`, `currency`, `opening`, `reviewed`, `description`, `arguments`, `language`, `extra`, `journalReferenceUuid`, `createdBy`, `updatedBy`, `revision`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (JournalEntry::all() as $entry) {
            $lines[] = $glue . '('
                . $entry->journalEntryId
                . ", '$entry->transDate'"
                . ", '$entry->domainUuid'"
                . ', ' . $this->exportNullable($entry->subJournalUuid)
                . ", '$entry->currency'"
                . ", " . (int) $entry->opening
                . ", " . (int) $entry->reviewed
                . ", '$entry->description'"
                . ", '" . json_encode($entry->arguments) . "'"
                . ", '$entry->language'"
                . ', ' . $this->exportNullable($entry->extra)
                . ', ' . $this->exportNullable($entry->journalReferenceUuid)
                . ', ' . $this->exportNullable($entry->createdBy)
                . ', ' . $this->exportNullable($entry->updatedBy)
                . ', ' . $this->exportNullable($entry->revision)
                . ', ' . $this->exportNullable($entry->created_at)
                . ', ' . $this->exportNullable($entry->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($entry);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`ledger_accounts` (`ledgerUuid`, `code`, `parentUuid`, `debit`, `credit`, `category`, `closed`, `extra`, `flex`, `revision`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (LedgerAccount::all() as $account) {
            $lines[] = $glue . '('
                . "'$account->ledgerUuid'"
                . ", '$account->code'"
                . ', ' . $this->exportNullable($account->parentUuid)
                . ", " . (int) $account->debit
                . ", " . (int) $account->credit
                . ", " . (int) $account->category
                . ", " . (int) $account->closed
                . ', ' . $this->exportNullable($account->extra)
                . ', ' . $this->exportNullable(json_encode($account->flex))
                . ', ' . $this->exportNullable($account->revision)
                . ', ' . $this->exportNullable($account->created_at)
                . ', ' . $this->exportNullable($account->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($account);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`ledger_balances` (`id`, `ledgerUuid`, `domainUuid`, `currency`, `balance`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (LedgerBalance::all() as $balance) {
            $lines[] = $glue . '('
                . "$balance->id"
                . ", '$balance->ledgerUuid'"
                . ", '$balance->domainUuid'"
                . ", '$balance->currency'"
                . ", '$balance->balance'"
                . ', ' . $this->exportNullable($balance->created_at)
                . ', ' . $this->exportNullable($balance->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($balance);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`ledger_currencies` (`code`, `decimals`, `revision`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (LedgerCurrency::all() as $currency) {
            $lines[] = $glue . '('
                . "'$currency->code'"
                . ', ' . $currency->decimals
                . ', ' . $this->exportNullable($currency->revision)
                . ', ' . $this->exportNullable($currency->created_at)
                . ', ' . $this->exportNullable($currency->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($currency);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`ledger_domains` (`domainUuid`, `code`, `extra`, `flex`, `currencyDefault`, `subJournals`, `revision`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (LedgerDomain::all() as $domain) {
            $lines[] = $glue . '('
                . "'$domain->domainUuid'"
                . ", '$domain->code'"
                . ', ' . $this->exportNullable($domain->extra)
                . ', ' . $this->exportNullable(json_encode($domain->flex))
                . ", '$domain->currencyDefault'"
                . ", " . (int) $domain->subJournals
                . ', ' . $this->exportNullable($domain->revision)
                . ', ' . $this->exportNullable($domain->created_at)
                . ', ' . $this->exportNullable($domain->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        unset($domain);
        $lines[] = '-- Table';
        $lines[] = $sqlBuster . '`ledger_names` (`id`, `ownerUuid`, `language`, `name`, `created_at`, `updated_at`) VALUES';
        $glue = '';
        foreach (LedgerName::all() as $name) {
            $lines[] = $glue . '('
                . $name->id
                . ", '$name->ownerUuid'"
                . ", '$name->language'"
                . ", '$name->name'"
                . ', ' . $this->exportNullable($name->created_at)
                . ', ' . $this->exportNullable($name->updated_at)
                . ')';
            $glue = ',';
        }
        $lines[] = ';';
        file_put_contents($toPath, implode("\n", $lines));
    }

    private function hasAttributes(array $attributes, object $object)
    {
        foreach ($attributes as $attribute) {
            $this->assertTrue(isset($object->$attribute));
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
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);
        $asArray = (array)$actual;
        $this->assertArrayHasKey('time', $asArray);
        $this->assertArrayHasKey('errors', $asArray);
        if (count($asArray) !== 2) {
            $this->assertArrayHasKey('version', $asArray);
            $this->assertArrayHasKey('apiVersion', $asArray);
            $this->assertCount(4, $asArray);
        }

        return $actual;
    }

    /**
     * Make sure the response was not an error and is well-structured.
     * @param TestResponse $response
     * @param string|null $expect
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
        if (isset($response['errors'])) {
            print_r($response['errors']);
        }
        $this->assertFalse(isset($response['errors']));
        if ($expectContent !== '') {
            $this->assertTrue(isset($response[$expectContent]));
        }
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);

        return $actual;
    }

}
