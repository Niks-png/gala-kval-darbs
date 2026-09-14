<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('category')->nullable()->index()->after('store');
            });
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE products SET category = CASE
            WHEN LOWER(title) REGEXP 'piens|siers|jogurt|kefir|sviest|kreen|biezpien' THEN 'Piena produkti'
            WHEN LOWER(title) REGEXP 'darzen|tomat|gurk|kartupe|burkan|sīpol|kapost|salat|paprik' THEN 'Dārzeņi'
            WHEN LOWER(title) REGEXP 'augl|abol|banan|apelsin|mandarin|vīnog|bumbier|zemen' THEN 'Augļi'
            WHEN LOWER(title) REGEXP 'maiz|bulcin|baget|tost|rupjmaiz' THEN 'Maize un konditoreja'
            WHEN LOWER(title) REGEXP 'gal|vista|cūk|liellop|des|skink|bekon|filej' THEN 'Gaļa'
            WHEN LOWER(title) REGEXP 'ziv|lasis|tunc|silk|garnel' THEN 'Zivis un jūras veltes'
            WHEN LOWER(title) REGEXP 'sald|sokolad|konfekt|cepum|cips|uzkod|desert' THEN 'Saldumi un uzkodas'
            WHEN LOWER(title) REGEXP 'dzer|sula|udens|kafij|tej|alus|vins|limonad' THEN 'Dzērieni'
            ELSE 'Citi'
        END WHERE category IS NULL");
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
