<?php

declare(strict_types=1);

use App\Livewire\SystemOneStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('shows a green dot when the model is loaded', function (): void {
    Http::fake(['*/api/ps' => Http::response(['models' => [['name' => 'd3:4b']]])]);

    Livewire::withoutLazyLoading()->test(SystemOneStatus::class, ['model' => 'd3:4b'])
        ->assertSeeHtml('bg-online')
        ->assertSee('Model ready')
        ->assertSeeHtml('wire:poll.10s');
});

it('shows an amber dot when Ollama has the model unloaded', function (): void {
    Http::fake(['*/api/ps' => Http::response(['models' => []])]);

    Livewire::withoutLazyLoading()->test(SystemOneStatus::class, ['model' => 'd3:2b'])
        ->assertSeeHtml('bg-s1')
        ->assertSee('Model asleep, wakes on the next decision');
});

it('shows a red dot when Ollama cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('refused'));

    Livewire::withoutLazyLoading()->test(SystemOneStatus::class, ['model' => 'd3:2b'])
        ->assertSeeHtml('bg-offline')
        ->assertSee('Ollama offline, the bot decides instead');
});

it('only checks Ollama, never asks the model a question', function (): void {
    Http::fake(['*/api/ps' => Http::response(['models' => []])]);

    Livewire::withoutLazyLoading()->test(SystemOneStatus::class, ['model' => 'd3:2b']);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/api/ps'));
});
