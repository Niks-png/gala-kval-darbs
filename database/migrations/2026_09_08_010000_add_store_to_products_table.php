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
        if (! Schema::hasColumn('products', 'store')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('store')->default('unknown')->after('title');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['title', 'store']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'store')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('store');
            });
        }
    }
};
