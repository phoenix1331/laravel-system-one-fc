<?php

declare(strict_types=1);

use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->seed(TeamSeeder::class);
});

it('plays a full match between two bots', function (): void {
    $this->artisan('fc:play', ['home' => 'Bala Town', 'away' => 'Penybont', '--seed' => 42])
        ->expectsOutputToContain('Bala Town v Penybont, seed 42')
        ->expectsOutputToContain('Full time: Bala Town')
        ->expectsOutputToContain('Replay this match with --seed=42')
        ->assertSuccessful();
});

it('finds clubs by part of their name', function (): void {
    $this->artisan('fc:play', ['home' => 'Saints', 'away' => 'Cardiff', '--seed' => 1])
        ->expectsOutputToContain('The New Saints v Cardiff Met')
        ->assertSuccessful();
});

it('plays the same match twice from the same seed', function (): void {
    Artisan::call('fc:play', ['home' => 'Bala', 'away' => 'Barry', '--seed' => 9]);
    $first = Artisan::output();

    Artisan::call('fc:play', ['home' => 'Bala', 'away' => 'Barry', '--seed' => 9]);

    expect(Artisan::output())->toBe($first);
});

it('picks a random seed when none is given', function (): void {
    $this->artisan('fc:play', ['home' => 'Bala', 'away' => 'Barry'])
        ->expectsOutputToContain('Replay this match with --seed=')
        ->assertSuccessful();
});

it('fails with the list of clubs when a name is unknown', function (): void {
    $this->artisan('fc:play', ['home' => 'Wrexham', 'away' => 'Bala Town'])
        ->expectsOutputToContain('No club called "Wrexham"')
        ->assertFailed();
});
