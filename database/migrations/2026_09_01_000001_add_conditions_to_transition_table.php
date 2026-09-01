<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarding conditions for a transition, stored like actions:
     * a JSON array of { type: <condition key>, config: {...} }.
     * Null / empty means the transition is unconditionally reachable.
     */
    public function up(): void
    {
        Schema::table('transition', static function (Blueprint $table): void {
            $table->json('conditions')->nullable()->after('actions');
        });
    }

    public function down(): void
    {
        Schema::table('transition', static function (Blueprint $table): void {
            $table->dropColumn('conditions');
        });
    }
};
