<?php

declare(strict_types=1);

namespace App\Engine;

enum Substitution: string
{
    case None = 'none';
    case FreshLegs = 'fresh_legs';
    case ExtraStriker = 'extra_striker';
    case ExtraDefender = 'extra_defender';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No change',
            self::FreshLegs => 'Fresh legs',
            self::ExtraStriker => 'Extra striker',
            self::ExtraDefender => 'Extra defender',
        };
    }

    /**
     * How the commentary describes it, e.g. "bringing on an extra striker".
     */
    public function commentary(): string
    {
        return match ($this) {
            self::None => 'with no changes',
            self::FreshLegs => 'bringing on fresh legs',
            self::ExtraStriker => 'bringing on an extra striker',
            self::ExtraDefender => 'bringing on an extra defender',
        };
    }
}
