<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class CreateLedgerTables extends Migration
{
    const ACCOUNT_CODE_SIZE = 32;
    const AMOUNT_SIZE = 32;
    const CURRENCY_CODE_SIZE = 16;
    const LANGUAGE_CODE_SIZE = 8;

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('ledger_account_names');
        Schema::drop('ledger_domains');
        Schema::drop('ledger_currencies');
        Schema::drop('ledger_balances');
        Schema::drop('ledger_account_names');
        Schema::drop('ledger_accounts');
        Schema::drop('journal_entries');
        Schema::drop('journal_references');
        Schema::drop('journal_details');
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
            $table->string('amount', self::AMOUNT_SIZE);

            // Reference to an external entity.
            $table->uuid('journalReferenceUuid')->nullable();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            // Primary key
            $table->bigIncrements('journalEntryId');

            $table->timestamp('transDate');
            $table->foreignUuid('domainUuid');
            $table->uuid('subJournalUuid')->nullable();
            $table->string('currency', self::CURRENCY_CODE_SIZE);
            $table->tinyInteger('posted');
            $table->tinyInteger('reviewed');
            // Description
            $table->string('description');
            // Description arguments for translation
            $table->longText('arguments');
            // Language this description is in.
            $table->string('language', 8);
            $table->longText('extra')->nullable();
            $table->string('createdBy')->nullable();
            $table->string('updatedBy')->nullable();
            // The update timestamp (server-side)
            $table->timestamp('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Connection to entities outside the GL
        Schema::create('journal_references', function (Blueprint $table) {
            $table->uuid('journalReferenceUuid')->primary();
            $table->string('code')->unique();
            $table->longText('extra')->nullable();
            $table->timestamps(6);
        });

        // Account definitions (chart of accounts)
        Schema::create('ledger_accounts', function (Blueprint $table) {
            // Invariant account identifier
            $table->uuid('ledgerUuid')->primary();
            // User accessible account identifier
            $table->string('code', self::ACCOUNT_CODE_SIZE)->unique();
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
            $table->timestamp('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Actual account balances by domain and currency.
        Schema::create('ledger_balances', function (Blueprint $table) {
            // Primary key, just use default since access is composite
            $table->bigIncrements('id');
            $table->foreignUuid('ledgerUuid');
            $table->foreignUuid('domainUuid');
            $table->string('currency', self::CURRENCY_CODE_SIZE);
            $table->string('balance', self::AMOUNT_SIZE);
            $table->timestamps(6);

            $table->unique(['ledgerUuid', 'domainUuid', 'currency']);
        });

        // Ledger currencies
        Schema::create('ledger_currencies', function (Blueprint $table) {
            $table->string('code', self::CURRENCY_CODE_SIZE)->primary();
            $table->integer('decimals');
            $table->timestamp('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Ledger domains
        Schema::create('ledger_domains', function (Blueprint $table) {
            $table->uuid('domainUuid')->primary();
            $table->string('code', self::ACCOUNT_CODE_SIZE)->unique();
            $table->longText('extra')->nullable();
            $table->json('flex')->nullable();
            $table->string('currencyDefault', self::CURRENCY_CODE_SIZE);
            $table->boolean('subJournals')->default(false);
            $table->timestamp('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

        // Names in ledger related tables, internationalized.
        Schema::create('ledger_names', function (Blueprint $table) {
            // Primary key, just use default since access is composite
            $table->bigIncrements('id');

            $table->uuid('ownerUuid');
            $table->string('language', self::LANGUAGE_CODE_SIZE);
            $table->string('name');
            $table->timestamps(6);

            $table->unique(['ownerUuid', 'language']);
        });

        Schema::create('sub_journals', function (Blueprint $table) {
            $table->uuid('subJournalUuid')->primary();
            $table->string('code', self::ACCOUNT_CODE_SIZE);
            $table->longText('extra')->nullable();
            $table->timestamp('revision', 6)
                ->useCurrentOnUpdate()->nullable();
            $table->timestamps(6);
        });

    }

}
