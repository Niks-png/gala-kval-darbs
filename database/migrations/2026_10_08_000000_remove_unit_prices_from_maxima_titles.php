<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Maxima scraper used to keep the unit prices at the end of titles
 * ("Sviests LATGALE, 200 g (5,95 €/kg); (14,95 €/kg)"). It now removes them, so
 * clean the stored titles the same way; otherwise the next import would see
 * every product as new and cut it off from its price history and followers.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = DB::table('products')
            ->where('store', 'maxima.lv')
            ->where('title', 'like', '%€/%')
            ->get(['id', 'title']);

        foreach ($products as $product) {
            // Same rule as split_title() in scrapers/maxima_scraper.py.
            $title = (string) preg_replace('/\(\s*(no\s+)?[\d.,]*\s*€\/\s*[^)]*?\s*\)\s*;?/iu', ' ', $product->title);
            $title = rtrim((string) preg_replace('/\s+/u', ' ', trim($title)), ' ,;');

            $taken = DB::table('products')
                ->where('store', 'maxima.lv')
                ->where('title', $title)
                ->where('id', '!=', $product->id)
                ->exists();

            // If a clean copy already exists, leave this one; the next import marks it as ended.
            if ($title !== '' && ! $taken) {
                DB::table('products')->where('id', $product->id)->update(['title' => $title]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The removed unit prices are not stored anywhere, so titles cannot be restored.
    }
};
