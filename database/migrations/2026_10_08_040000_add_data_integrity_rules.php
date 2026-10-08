<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level rules, so bad values are refused even if they come from code that skips the
 * application's checks (or someone editing the database by hand).
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, string>> table => constraint name => condition
     */
    private const CHECKS = [
        'products' => [
            'products_current_price_not_negative' => 'current_price IS NULL OR current_price >= 0',
            'products_unit_price_not_negative' => 'unit_price IS NULL OR unit_price >= 0',
        ],
        'shopping_list_items' => [
            'shopping_list_items_quantity_range' => 'quantity BETWEEN 1 AND 99',
            'shopping_list_items_price_not_negative' => 'price_at_completion IS NULL OR price_at_completion >= 0',
        ],
        'shopping_list_user' => [
            'shopping_list_user_role_known' => "role IN ('editor', 'viewer')",
        ],
        'shopping_list_invitations' => [
            'shopping_list_invitations_role_known' => "role IN ('editor', 'viewer')",
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Shop CDN image addresses can be longer than the default 255 characters.
            $table->string('image_url', 2048)->nullable()->change();
        });

        // SQLite (used by the tests) cannot add constraints to an existing table.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (self::CHECKS as $table => $checks) {
                foreach (array_keys($checks) as $name) {
                    DB::statement("ALTER TABLE {$table} DROP CHECK {$name}");
                }
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });
    }
};
