<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('prompt_text');
            $table->timestamps();

            $table->unique(['challenge_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_days');
    }
};
