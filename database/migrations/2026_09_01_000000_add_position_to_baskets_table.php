<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store each basket's saved position on the designer canvas ({x, y}).
     * Null means "not yet placed" — the designer auto-lays it out instead.
     */
    public function up(): void
    {
        Schema::table('baskets', static function (Blueprint $table): void {
            $table->json('position')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('baskets', static function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
