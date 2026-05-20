<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_day_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->timestamps();

            $table->unique(['user_challenge_id', 'challenge_day_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
