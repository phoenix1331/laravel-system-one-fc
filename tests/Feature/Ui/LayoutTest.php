<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // The home page starts warming up the model in the background.
    Queue::fake();
});

it('renders the layout with the logo, theme toggle and self-hosted assets', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('System One FC')
        ->assertSee('aria-label="Switch theme"', escape: false)
        ->assertSee('localStorage', escape: false)
        ->assertDontSee('fonts.googleapis.com')
        ->assertDontSee('fonts.bunny.net');
});

it('shows the GitHub link only when a repository is configured', function (): void {
    $this->get(route('home'))->assertDontSee('>GitHub<', escape: false);

    config(['app.repository_url' => 'https://github.com/example/system-one-fc']);

    $this->get(route('home'))->assertSee('https://github.com/example/system-one-fc');
});
