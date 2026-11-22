<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Livewire\Pages\Home;
use App\Models\Game;
use App\Models\Team;
use Database\Seeders\TeamSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(TeamSeeder::class);
    Queue::fake();
});

it('shows the pitch, the clubs and the configured model', function (): void {
    config(['manager.model' => 'd3:4b']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Home::class)
        ->assertSee('Can you beat a System One manager?')
        ->assertSee('Bala Town')
        ->assertSee('Random opponent')
        ->assertSee('90 minutes takes about 3 minutes to play')
        ->assertSee('d3:4b')
        ->assertSee('Club ratings are invented for the game');
});

it('preselects the first club alphabetically and shows its ratings', function (): void {
    $first = Team::query()->orderBy('name')->first();

    Livewire::test(Home::class)
        ->assertSet('teamId', $first->id)
        ->assertSee('aria-valuenow="'.$first->attack.'"', escape: false);
});

it('updates the rating bars when another club is picked', function (): void {
    $saints = Team::query()->where('name', 'The New Saints')->first();

    Livewire::test(Home::class)
        ->set('teamId', $saints->id)
        ->assertSee('The New Saints ratings')
        ->assertSee('aria-valuenow="84"', escape: false);
});

it('kicks off against a chosen opponent and opens the match', function (): void {
    $bala = Team::query()->where('name', 'Bala Town')->first();
    $penybont = Team::query()->where('name', 'Penybont')->first();

    Livewire::test(Home::class)
        ->set('teamId', $bala->id)
        ->set('opponentId', $penybont->id)
        ->call('kickOff')
        ->assertRedirect(route('games.show', Game::query()->first()));

    $game = Game::query()->first();

    expect($game->human_team_id)->toBe($bala->id)
        ->and($game->model_team_id)->toBe($penybont->id)
        ->and($game->model)->toBe(config('manager.model'))
        ->and($game->status)->toBe(GameStatus::Deciding);
});

it('picks a random opponent that is never your own club', function (): void {
    $bala = Team::query()->where('name', 'Bala Town')->first();

    foreach (range(1, 10) as $attempt) {
        Livewire::test(Home::class)->set('teamId', $bala->id)->call('kickOff');
    }

    expect(Game::query()->where('model_team_id', $bala->id)->exists())->toBeFalse()
        ->and(Game::query()->count())->toBe(10);
});

it('will not play a club against itself', function (): void {
    $bala = Team::query()->where('name', 'Bala Town')->first();

    Livewire::test(Home::class)
        ->set('teamId', $bala->id)
        ->set('opponentId', $bala->id)
        ->call('kickOff')
        ->assertHasErrors(['opponentId' => 'different']);
});

it('needs a real club', function (): void {
    Livewire::test(Home::class)
        ->set('teamId', 999)
        ->call('kickOff')
        ->assertHasErrors(['teamId' => 'exists']);
});
