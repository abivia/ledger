<?php

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class LedgerCreateTablesV2 extends Migration
{
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('journal_details');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_references');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('ledger_balances');
        Schema::dropIfExists('ledger_currencies');
        Schema::dropIfExists('ledger_domains');
        Schema::dropIfExists('ledger_names');
        Schema::dropIfExists('ledger_reports');
        Schema::dropIfExists('sub_journals');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Line item in a journal entry.
        Schema::create('journal_details', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('journalDetailId');

            // The journal entry
            $table->bigInteger('journalEntryId')->index();

            // Ledger account identifier
            $table->uuid('ledgerUuid')->index();

            // The [split] transaction amount
            $table->string('amount', LedgerCurrency::AMOUNT_SIZE);

            // Reference to an external entity.
            $table->uuid('journalReferenceUuid')->nullable();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('journalEntryId');

            $table->dateTime('transDate');
            $table->foreignUuid('domainUuid');
            $table->uuid('subJournalUuid')->nullable();
            $table->string('currency', LedgerCurrency::CODE_SIZE);
            $table->tinyInteger('opening');
            $table->tinyInteger('clearing')->default(false);
            $table->tinyInteger('reviewed');
            $table->tinyInteger('locked')->default(false);
            // Description
            $table->string('description');
            // Description arguments for translation
            $table->longText('arguments');
            // Language this description is in.
            $table->string('language', 8);
            $table->longText('extra')->nullable();
            // Reference to an external entity.
            $table->uuid('journalReferenceUuid')->nullable();
            $table->string('createdBy')->nullable();
            $table->string('updatedBy')->nullable();
            // The update timestamp (server-side)
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Connection to entities outside the GL
        Schema::create('journal_references', function (Blueprint $table) {
            $table->uuid('journalReferenceUuid')->primary();
            $table->foreignUuid('domainUuid');
            $table->string('code');
            $table->longText('extra')->nullable();
            // The update timestamp (server-side)
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);

            $table->unique(['domainUuid', 'code']);
        });

        // Account definitions (chart of accounts)
        Schema::create('ledger_accounts', function (Blueprint $table) {
            // Invariant account identifier
            $table->uuid('ledgerUuid')->primary();
            // User accessible account identifier
            $table->string('code', LedgerAccount::CODE_SIZE)->unique();
            // The account code for reporting / regulatory purposes.
            $table->string('taxCode', LedgerAccount::CODE_SIZE)
                ->nullable()
                ->index();
            $table->uuid('parentUuid')->nullable()->index();
            // Debit/credit flags: only the root is neither, otherwise one must be set.
            $table->boolean('debit');
            $table->boolean('credit');
            // Category accounts: parent must be root or category, no balance/transactions
            $table->boolean('category');
            $table->boolean('closed');
            $table->longText('extra')->nullable();
            $table->json('flex')->nullable();
            // The update timestamp (server-side)
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Actual account balances by domain and currency.
        Schema::create('ledger_balances', function (Blueprint $table) {
            // Primary key, just use default since access is composite
            $table->bigIncrements('id');
            $table->foreignUuid('ledgerUuid');
            $table->foreignUuid('domainUuid');
            $table->string('currency', LedgerCurrency::CODE_SIZE);
            $table->string('balance', LedgerCurrency::AMOUNT_SIZE);
            $table->timestamps(6);

            $table->unique(['ledgerUuid', 'domainUuid', 'currency']);
        });

        // Ledger currencies
        Schema::create('ledger_currencies', function (Blueprint $table) {
            $table->string('code', LedgerCurrency::CODE_SIZE)->primary();
            $table->integer('decimals');
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Ledger domains
        Schema::create('ledger_domains', function (Blueprint $table) {
            $table->uuid('domainUuid')->primary();
            $table->string('code', LedgerAccount::CODE_SIZE)->unique();
            $table->longText('extra')->nullable();
            $table->json('flex')->nullable();
            $table->string('currencyDefault', LedgerCurrency::CODE_SIZE);
            $table->boolean('subJournals')->default(false);
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Names in ledger related tables, internationalized.
        Schema::create('ledger_names', function (Blueprint $table) {
            // Primary key, just use default since access is composite
            $table->bigIncrements('id');

            $table->uuid('ownerUuid');
            $table->string('language', LedgerName::CODE_SIZE);
            $table->string('name');
            $table->timestamps(6);

            $table->unique(['ownerUuid', 'language']);
        });

        // Cached financial reports
        Schema::create('ledger_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->index();
            $table->foreignUuid('domainUuid');
            $table->string('currency', LedgerCurrency::CODE_SIZE);
            $table->date('fromDate')->nullable();
            $table->date('toDate')->index();
            // The last journal entry when the report was generated.
            $table->bigInteger('journalEntryId');
            $table->longText('reportData');

        });

        Schema::create('sub_journals', function (Blueprint $table) {
            $table->uuid('subJournalUuid')->primary();
            $table->string('code', LedgerAccount::CODE_SIZE);
            $table->longText('extra')->nullable();
            $table->dateTime('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

    }

}
