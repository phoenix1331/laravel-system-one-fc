<?php

declare(strict_types=1);

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
