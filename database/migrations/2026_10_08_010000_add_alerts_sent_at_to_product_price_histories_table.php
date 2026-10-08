<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {
            // Set once followers were told about this price drop, so each drop is announced exactly once
            // and drops whose alerts failed are sent on the next import.
            $table->timestamp('alerts_sent_at')->nullable()->index()->after('new_price');
        });

        // Changes recorded before this column existed were already announced.
        DB::table('product_price_histories')->update(['alerts_sent_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_price_histories', function (Blueprint $table) {
            $table->dropColumn('alerts_sent_at');
        });
    }
};
