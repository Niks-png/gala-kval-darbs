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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Store domain the product was scraped from ("rimi.lv", "etop.lv", ...).
            $table->string('store');
            // Text, not a number: top! gives the regular price as a range ("5.59 € - 5.99 €").
            $table->string('original_price')->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('unit')->nullable();
            $table->string('image_url')->nullable();
            $table->string('category')->nullable()->index();
            $table->timestamps();

            // Shops sell products with the same name, so a product is a title within one store.
            $table->unique(['title', 'store']);
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
