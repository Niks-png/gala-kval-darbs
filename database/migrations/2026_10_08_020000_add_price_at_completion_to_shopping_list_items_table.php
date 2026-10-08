<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            // The product's price when the list was finished, so the purchase history keeps showing
            // what it cost then instead of today's price. Null while the list is open and for items
            // that were not bought.
            $table->decimal('price_at_completion', 10, 2)->nullable()->after('checked_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('price_at_completion');
        });
    }
};
