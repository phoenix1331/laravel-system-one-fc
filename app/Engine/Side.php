<?php

declare(strict_types=1);

namespace App\Engine;

enum Side: string
{
    case Home = 'home';
    case Away = 'away';

    public function opponent(): self
    {
        return $this === self::Home ? self::Away : self::Home;
    }
}
