<?php

declare(strict_types=1);

namespace App\Models;

use App\Engine\Mentality;
use App\Engine\Substitution;
use App\Enums\Dugout;
use App\Managers\Decision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A decision made by one dugout at one decision point, with the model's probabilities when a model made it.
 *
 * Called GameDecision so it does not clash with the App\Managers\Decision value object.
 *
 * @property int $id
 * @property int $game_id
 * @property int $decision_point
 * @property string $minute
 * @property string $score
 * @property Dugout $side
 * @property string $manager
 * @property Mentality $mentality
 * @property bool $press_high
 * @property Substitution|null $substitution
 * @property int|null $concern
 * @property array<string, array<string, float>>|null $probabilities
 * @property bool $fallback_used
 * @property int|null $response_ms
 * @property-read Game $game
 */
#[Table('decisions')]
#[Fillable(['game_id', 'decision_point', 'minute', 'score', 'side', 'manager', 'mentality', 'press_high', 'substitution', 'concern', 'probabilities', 'fallback_used', 'response_ms'])]
class GameDecision extends Model
{
    protected function casts(): array
    {
        return [
            'decision_point' => 'integer',
            'side' => Dugout::class,
            'mentality' => Mentality::class,
            'press_high' => 'boolean',
            'substitution' => Substitution::class,
            'concern' => 'integer',
            'probabilities' => 'array',
            'fallback_used' => 'boolean',
            'response_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function toDecision(): Decision
    {
        return new Decision(
            mentality: $this->mentality,
            pressHigh: $this->press_high,
            substitution: $this->substitution,
            probabilities: $this->probabilities,
            concern: $this->concern,
            fallbackUsed: $this->fallback_used,
            responseMs: $this->response_ms,
        );
    }
}
