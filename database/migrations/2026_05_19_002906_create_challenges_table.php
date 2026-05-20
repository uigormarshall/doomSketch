<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('original_challenge_id')->nullable()->constrained('challenges')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_private')->default(false);
            $table->boolean('has_palette')->default(false);
            $table->string('palette_name')->nullable();
            $table->json('palette_colors')->nullable();
            $table->timestamps();

            $table->index(['is_private', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
