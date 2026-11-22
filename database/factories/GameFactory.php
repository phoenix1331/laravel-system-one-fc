<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use App\Simulation\EngineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Prefer GameRunner::start() in tests that play the game; this factory is for games that are only displayed.
 *
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $human = Team::factory()->create();
        $model = Team::factory()->create();
        $engines = app(EngineFactory::class);
        $random = $engines->randomizer(1);
        $state = $engines->withRandomizer($random)->kickOff($human->toTeamState(), $model->toTeamState());

        return [
            'human_team_id' => $human->id,
            'model_team_id' => $model->id,
            'seed' => 1,
            'model' => 'd3:2b',
            'status' => GameStatus::InProgress,
            'engine_state' => $state->toArray(),
            'random_state' => base64_encode(serialize($random)),
        ];
    }

    /**
     * A finished game with a final score.
     */
    public function finished(int $humanScore, int $modelScore): static
    {
        return $this->state(fn (): array => [
            'status' => GameStatus::Finished,
            'clock' => "90+2'",
            'human_score' => $humanScore,
            'model_score' => $modelScore,
        ]);
    }
}
