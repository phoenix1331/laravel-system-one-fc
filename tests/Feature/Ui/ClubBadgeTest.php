<?php

declare(strict_types=1);

use App\Models\Team;
use Database\Seeders\TeamSeeder;

it('finds the badge for each seeded club', function (string $name): void {
    expect(Team::badgeUrl($name))->toEndWith('/img/badges/'.Str::slug($name).'.png');
})->with(array_keys(TeamSeeder::CLUBS));

it('has no badge for an unknown club', function (): void {
    expect(Team::badgeUrl('Wrexham Rovers'))->toBeNull();
});

it('renders a badge image, or the initial when there is no badge', function (): void {
    $this->blade('<x-club-badge name="Bala Town" />')
        ->assertSee('img/badges/bala-town.png')
        ->assertSee('alt=""', escape: false);

    $this->blade('<x-club-badge name="Wrexham Rovers" />')
        ->assertDontSee('<img', escape: false)
        ->assertSee('W');
});

it('credits footylogos.com for the badges', function (): void {
    $this->seed(TeamSeeder::class);

    $this->get(route('home'))
        ->assertSee('https://www.footylogos.com/')
        ->assertSee('img/badges/', escape: false);
});
