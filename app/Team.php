<?php

declare(strict_types=1);

namespace App;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;
}
