<?php

declare(strict_types=1);

use App\Livewire\Pages\FullTime;
use App\Livewire\Pages\Home;
use App\Livewire\Pages\PlayGame;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/games/{game}', PlayGame::class)->name('games.show');
Route::livewire('/games/{game}/result', FullTime::class)->name('games.result');
