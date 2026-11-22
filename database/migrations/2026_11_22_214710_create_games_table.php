<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('human_team_id')->constrained('teams');
            $table->foreignId('model_team_id')->constrained('teams');
            $table->unsignedBigInteger('seed');
            $table->string('model');
            $table->string('status')->index();
            $table->string('clock')->default("0'");
            $table->unsignedInteger('decision_point')->default(0);
            $table->json('engine_state');
            $table->longText('random_state');
            $table->unsignedTinyInteger('human_score')->default(0);
            $table->unsignedTinyInteger('model_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
