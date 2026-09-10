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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'original_price')) {
                    $table->string('original_price')->nullable();
                }
                if (! Schema::hasColumn('products', 'current_price')) {
                    $table->decimal('current_price', 10, 2)->nullable();
                }
            });

            return;
        }

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->string('original_price')->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
