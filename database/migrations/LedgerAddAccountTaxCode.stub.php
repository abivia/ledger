<?php

use Abivia\Ledger\Models\LedgerAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


class LedgerAddAccountTaxCode extends Migration
{
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn('taxCode');
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
        Schema::table('ledger_accounts', function (Blueprint $table) {
            // The account code for reporting / regulatory purposes.
            $table->string('taxCode', LedgerAccount::CODE_SIZE)
                ->nullable()
                ->after('code')
                ->index();
        });

    }

}
