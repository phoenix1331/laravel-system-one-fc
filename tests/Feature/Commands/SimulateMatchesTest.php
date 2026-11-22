<?php

declare(strict_types=1);

use Database\Seeders\TeamSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

function fakeModel(): void
{
    Http::fake(['*' => Http::response(json_decode(file_get_contents(__DIR__.'/../SystemOne/fixtures/decision.json'), true))]);
}

it('benchmarks System One against the bot and reports the results', function (): void {
    $this->seed(TeamSeeder::class);
    fakeModel();

    expect(Artisan::call('fc:simulate', ['count' => 3]))->toBe(0)
        ->and(Artisan::output())
        ->toContain('System One (d3:2b) v the rule-based bot, 3 matches')
        ->toContain('Won / drawn / lost')
        ->toMatch('/Fallbacks\s+\| 0 of \d+ decisions/');
});

it('repeats the same benchmark from the same seed', function (): void {
    $this->seed(TeamSeeder::class);
    fakeModel();

    Artisan::call('fc:simulate', ['count' => 2, '--seed' => 5]);
    $first = preg_replace('/Average response.*\n/', '', Artisan::output());

    Artisan::call('fc:simulate', ['count' => 2, '--seed' => 5]);

    expect(preg_replace('/Average response.*\n/', '', Artisan::output()))->toBe($first);
});

it('benchmarks a named model', function (): void {
    $this->seed(TeamSeeder::class);
    fakeModel();

    $this->artisan('fc:simulate', ['count' => 1, '--model' => 'd3:4b'])
        ->expectsOutputToContain('System One (d3:4b)')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => ($request->data()['model'] ?? null) === 'd3:4b');
});

it('counts fallbacks when the model cannot answer', function (): void {
    $this->seed(TeamSeeder::class);
    Http::fake(fn () => throw new ConnectionException('refused'));

    Artisan::call('fc:simulate', ['count' => 1]);

    expect(Artisan::output())->toMatch('/Fallbacks\s+\| (\d+) of \1 decisions/')
        ->toMatch('/Average response\s+\| n\/a/');
});

it('needs clubs to play with', function (): void {
    $this->artisan('fc:simulate', ['count' => 1])
        ->expectsOutputToContain('Seed the clubs first')
        ->assertFailed();
});
