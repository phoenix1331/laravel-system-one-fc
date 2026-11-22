<?php

declare(strict_types=1);

use App\Models\Team;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TeamSeeder;

it('seeds the eight clubs and can be run twice', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(TeamSeeder::class);

    expect(Team::query()->count())->toBe(8)
        ->and(Team::query()->where('name', "Connah's Quay Nomads")->exists())->toBeTrue();
});

it('gives every seeded club ratings the engine accepts', function (): void {
    $this->seed(TeamSeeder::class);

    Team::query()->each(function (Team $team): void {
        expect($team->ratings()->toArray())->each->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(100);
    });
});

it('builds a fresh engine team at full base fitness', function (): void {
    $team = Team::factory()->create(['attack' => 70, 'midfield' => 65, 'defence' => 60, 'fitness' => 80]);

    $state = $team->toTeamState();

    expect($state->name)->toBe($team->name)
        ->and($state->ratings->attack)->toBe(70)
        ->and($state->currentFitness())->toBe(80.0)
        ->and($state->goals)->toBe(0);
});
