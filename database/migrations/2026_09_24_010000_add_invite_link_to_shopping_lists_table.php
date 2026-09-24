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
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('name');
            $table->string('invite_role')->default('editor')->after('invite_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['invite_token', 'invite_role']);
        });
    }
};
