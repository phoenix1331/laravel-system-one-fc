<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Cymru Premier clubs with invented ratings. No real players, no real data.
 */
class TeamSeeder extends Seeder
{
    /**
     * Name => [attack, midfield, defence, fitness]. Spread so there are clear favourites and underdogs.
     *
     * @var array<string, array{int, int, int, int}>
     */
    public const CLUBS = [
        'The New Saints' => [84, 80, 78, 82],
        "Connah's Quay Nomads" => [72, 70, 71, 74],
        'Bala Town' => [69, 68, 70, 72],
        'Penybont' => [68, 66, 67, 73],
        'Caernarfon Town' => [66, 64, 62, 70],
        'Barry Town United' => [63, 63, 64, 68],
        'Haverfordwest County' => [61, 62, 63, 69],
        'Cardiff Met' => [57, 60, 58, 78],
    ];

    public function run(): void
    {
        foreach (self::CLUBS as $name => [$attack, $midfield, $defence, $fitness]) {
            Team::query()->updateOrCreate(
                ['name' => $name],
                ['attack' => $attack, 'midfield' => $midfield, 'defence' => $defence, 'fitness' => $fitness],
            );
        }
    }
}
