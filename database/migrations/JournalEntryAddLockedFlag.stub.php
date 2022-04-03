<?php

use Abivia\Ledger\Models\LedgerAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class JournalEntryAddLockedFlag extends Migration
{
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('locked');
        });
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Account definitions (chart of accounts)
        Schema::table('journal_entries', function (Blueprint $table) {
            // The account code for reporting / regulatory purposes.
            $table->tinyInteger('locked')
                ->default(false)
                ->after('reviewed');
        });

    }

}
