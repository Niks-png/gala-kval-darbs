<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The products table had drifted from its migrations: ImportProductsCommand
     * writes a "price" column and the search page filters by "category" and
     * "description", none of which were ever defined in a migration.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'category')) {
                $table->string('category')->nullable()->index();
            }
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('products', 'price')) {
                $table->decimal('price', 10, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'description', 'price']);
        });
    }
};
