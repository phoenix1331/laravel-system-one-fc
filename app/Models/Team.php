<?php

declare(strict_types=1);

namespace App\Models;

use App\Engine\TeamRatings;
use App\Engine\TeamState;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A club with invented ratings from 1 to 100.
 *
 * @property int $id
 * @property string $name
 * @property int $attack
 * @property int $midfield
 * @property int $defence
 * @property int $fitness
 */
#[Fillable(['name', 'attack', 'midfield', 'defence', 'fitness'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attack' => 'integer',
            'midfield' => 'integer',
            'defence' => 'integer',
            'fitness' => 'integer',
        ];
    }

    public function ratings(): TeamRatings
    {
        return new TeamRatings($this->attack, $this->midfield, $this->defence, $this->fitness);
    }

    /**
     * A fresh engine team, ready for kick-off.
     */
    public function toTeamState(): TeamState
    {
        return new TeamState($this->name, $this->ratings());
    }
}
