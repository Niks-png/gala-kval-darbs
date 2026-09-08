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
        Schema::table('products', function (Blueprint $table) {
            $table->string('store')->default('unknown')->after('title');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_title_unique');
            $table->unique(['title', 'store']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_title_store_unique');
            $table->unique('title');
            $table->dropColumn('store');
        });
    }
};
