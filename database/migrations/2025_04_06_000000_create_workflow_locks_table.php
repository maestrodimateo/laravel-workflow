<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_locks', static function (Blueprint $table): void {
            $table->id();
            $table->uuidMorphs('lockable');
            $table->string('locked_by');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['lockable_type', 'lockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_locks');
    }
};
