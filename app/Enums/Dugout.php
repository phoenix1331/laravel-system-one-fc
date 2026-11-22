<?php

declare(strict_types=1);

namespace App\Enums;

use App\Engine\Side;

/**
 * Who sits in each dugout. The player is always the home side, System One always away.
 */
enum Dugout: string
{
    case Human = 'human';
    case Model = 'model';

    public function side(): Side
    {
        return $this === self::Human ? Side::Home : Side::Away;
    }

    public static function fromSide(Side $side): self
    {
        return $side === Side::Home ? self::Human : self::Model;
    }
}
