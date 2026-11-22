<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Dugout;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in the match feed: a shot, a goal, a whistle or a tactical change.
 *
 * @property int $id
 * @property int $game_id
 * @property string $minute
 * @property string $type
 * @property Dugout|null $side
 * @property string $commentary
 * @property array<string, mixed>|null $payload
 * @property-read Game $game
 */
#[Fillable(['game_id', 'minute', 'type', 'side', 'commentary', 'payload'])]
class GameEvent extends Model
{
    public const DECISION = 'decision';

    protected function casts(): array
    {
        return [
            'side' => Dugout::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
