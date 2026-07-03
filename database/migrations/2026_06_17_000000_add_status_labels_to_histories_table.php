<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the human-readable label columns next to the status codes.
     */
    public function up(): void
    {
        Schema::table('histories', static function (Blueprint $table): void {
            $table->string('previous_status_label')->nullable()->after('previous_status');
            $table->string('next_status_label')->nullable()->after('next_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('histories', static function (Blueprint $table): void {
            $table->dropColumn(['previous_status_label', 'next_status_label']);
        });
    }
};