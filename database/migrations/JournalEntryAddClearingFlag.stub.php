<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class JournalEntryAddClearingFlag extends Migration
{
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn('clearing');
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
            // Lock flag to prevent update/delete operations.
            $table->tinyInteger('clearing')
                ->default(false)
                ->after('opening');
        });

    }

}
