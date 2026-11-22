<?php

declare(strict_types=1);

namespace App\Models;

use App\Engine\MatchState;
use App\Enums\Dugout;
use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One match between the player (home) and System One (away).
 *
 * Named Game rather than Match because match is a PHP keyword.
 *
 * @phpstan-import-type MatchStateArray from MatchState
 *
 * @property int $id
 * @property int $human_team_id
 * @property int $model_team_id
 * @property int $seed
 * @property string $model
 * @property GameStatus $status
 * @property string $clock
 * @property int $decision_point
 * @property MatchStateArray $engine_state
 * @property string $random_state
 * @property int $human_score
 * @property int $model_score
 * @property Carbon $created_at
 * @property-read Team $humanTeam
 * @property-read Team $modelTeam
 */
#[Fillable(['human_team_id', 'model_team_id', 'seed', 'model', 'status', 'clock', 'decision_point', 'engine_state', 'random_state', 'human_score', 'model_score'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'status' => GameStatus::class,
            'decision_point' => 'integer',
            'engine_state' => 'array',
            'human_score' => 'integer',
            'model_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function humanTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'human_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function modelTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'model_team_id');
    }

    /**
     * @return HasMany<GameDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(GameDecision::class);
    }

    /**
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function state(): MatchState
    {
        return MatchState::fromArray($this->engine_state);
    }

    /**
     * The decision a dugout made at the current decision point, if it has made one yet.
     */
    public function currentDecision(Dugout $dugout): ?GameDecision
    {
        return $this->decisions()
            ->where('decision_point', $this->decision_point)
            ->where('side', $dugout)
            ->first();
    }

    /**
     * Results of every finished game: human wins, draws and System One wins.
     *
     * @return array{human: int, draws: int, model: int}
     */
    public static function record(): array
    {
        $finished = self::query()->where('status', GameStatus::Finished);

        return [
            'human' => (clone $finished)->whereColumn('human_score', '>', 'model_score')->count(),
            'draws' => (clone $finished)->whereColumn('human_score', '=', 'model_score')->count(),
            'model' => (clone $finished)->whereColumn('human_score', '<', 'model_score')->count(),
        ];
    }

    public function isFinished(): bool
    {
        return $this->status === GameStatus::Finished;
    }

    public function isDeciding(): bool
    {
        return $this->status === GameStatus::Deciding;
    }
}
