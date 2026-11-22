<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('decision_point');
            $table->string('minute');
            $table->string('score');
            $table->string('side');
            $table->string('manager');
            $table->string('mentality');
            $table->boolean('press_high');
            $table->string('substitution')->nullable();
            $table->unsignedTinyInteger('concern')->nullable();
            $table->json('probabilities')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->unsignedInteger('response_ms')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'decision_point', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
