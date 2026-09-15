<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasTitleUniqueIndex = collect(Schema::getIndexes('products'))
            ->contains(fn (array $index): bool => $index['name'] === 'products_title_unique');

        if ($hasTitleUniqueIndex) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropUnique('products_title_unique');
            });
        }
    }

    public function down(): void
    {
        $hasTitleUniqueIndex = collect(Schema::getIndexes('products'))
            ->contains(fn (array $index): bool => $index['name'] === 'products_title_unique');

        if (! $hasTitleUniqueIndex) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unique('title');
            });
        }
    }
};
