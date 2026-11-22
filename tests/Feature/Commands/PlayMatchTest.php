<?php

declare(strict_types=1);

use Database\Seeders\TeamSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(TeamSeeder::class);
});

it('plays a full match between two bots', function (): void {
    $this->artisan('fc:play', ['home' => 'Bala Town', 'away' => 'Penybont', '--seed' => 42])
        ->expectsOutputToContain('Bala Town (rules) v Penybont (rules), seed 42')
        ->expectsOutputToContain('Full time: Bala Town')
        ->expectsOutputToContain('Replay this match with --seed=42')
        ->assertSuccessful();
});

it('finds clubs by part of their name', function (): void {
    $this->artisan('fc:play', ['home' => 'Saints', 'away' => 'Cardiff', '--seed' => 1])
        ->expectsOutputToContain('The New Saints (rules) v Cardiff Met (rules)')
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

it('lets System One manage the away side and prints its working', function (): void {
    $answers = json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true);
    Http::fake(['*' => Http::response($answers)]);

    $this->artisan('fc:play', ['home' => 'Bala', 'away' => 'Penybont', '--seed' => 3, '--model' => null])
        ->expectsOutputToContain('Penybont (System One (d3:2b))')
        ->expectsOutputToContain('BALANCED 0.52, attacking 0.21 | press 0.09 | concern 1/5')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'd3:2b' && $request['state']['my_team'] === 'Penybont');
});

it('can name a different model', function (): void {
    Http::fake(['*' => Http::response(json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true))]);

    $this->artisan('fc:play', ['home' => 'Bala', 'away' => 'Penybont', '--seed' => 3, '--model' => 'd3:4b'])
        ->expectsOutputToContain('System One (d3:4b)')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'd3:4b');
});

it('marks decisions where the model fell back to the bot', function (): void {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->artisan('fc:play', ['home' => 'Bala', 'away' => 'Penybont', '--seed' => 3, '--model' => null])
        ->expectsOutputToContain('[fallback] the model did not answer, so the rule-based bot chose')
        ->assertSuccessful();
});
