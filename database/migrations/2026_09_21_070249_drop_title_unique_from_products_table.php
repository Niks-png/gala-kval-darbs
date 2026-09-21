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
        if (Schema::hasIndex('products', 'products_title_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique('products_title_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('products', 'products_title_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique('title');
            });
        }
    }
};
