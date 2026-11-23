<?php

declare(strict_types=1);

namespace App\Simulation;

/**
 * Running results for one side across a benchmark.
 */
final class Tally
{
    public int $won = 0;

    public int $drawn = 0;

    public int $lost = 0;

    public int $goalsFor = 0;

    public int $goalsAgainst = 0;

    public function add(int $for, int $against): void
    {
        $this->goalsFor += $for;
        $this->goalsAgainst += $against;

        match ($for <=> $against) {
            1 => $this->won++,
            0 => $this->drawn++,
            -1 => $this->lost++,
        };
    }

    public function points(): int
    {
        return 3 * $this->won + $this->drawn;
    }

    public function record(): string
    {
        return "{$this->won} / {$this->drawn} / {$this->lost}";
    }

    public function goals(): string
    {
        return "{$this->goalsFor} / {$this->goalsAgainst}";
    }
}
