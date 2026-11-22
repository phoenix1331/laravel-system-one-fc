<?php

declare(strict_types=1);

namespace App\Managers;

use App\Engine\MatchState;
use App\Engine\Phase;
use App\Engine\Side;
use App\Engine\Tactics;

/**
 * A read-only snapshot of the match from one manager's side of the pitch.
 *
 * @phpstan-type ModelState array{
 *     minute: int,
 *     my_team: string,
 *     opponent: string,
 *     score: array{us: int, them: int},
 *     possession_last_15: int,
 *     shots_last_15: array{us: int, them: int},
 *     fitness: array{us: int, them: int},
 *     current_mentality: string,
 *     pressing: bool,
 *     subs_left: int,
 * }
 */
final readonly class DecisionContext
{
    public function __construct(
        public int $minute,
        public string $clock,
        public bool $isHalfTime,
        public string $myTeam,
        public string $opponent,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $possessionLast15,
        public int $shotsLast15For,
        public int $shotsLast15Against,
        public int $fitness,
        public int $opponentFitness,
        public Tactics $tactics,
        public int $subsLeft,
    ) {}

    public static function fromMatch(MatchState $state, Side $side, int $maxSubstitutions = 3): self
    {
        $us = $state->team($side);
        $them = $state->team($side->opponent());

        return new self(
            minute: $state->minute + $state->added,
            clock: $state->clock(),
            isHalfTime: $state->phase === Phase::HalfTime,
            myTeam: $us->name,
            opponent: $them->name,
            goalsFor: $us->goals,
            goalsAgainst: $them->goals,
            possessionLast15: $state->possessionLast15($side),
            shotsLast15For: $state->shotsLast15($side),
            shotsLast15Against: $state->shotsLast15($side->opponent()),
            fitness: (int) round($us->currentFitness()),
            opponentFitness: (int) round($them->currentFitness()),
            tactics: $us->tactics,
            subsLeft: max(0, $maxSubstitutions - $us->substitutionsUsed),
        );
    }

    /**
     * Positive when winning, negative when losing.
     */
    public function goalDifference(): int
    {
        return $this->goalsFor - $this->goalsAgainst;
    }

    /**
     * The JSON state sent to the decision model, written from the model's own perspective.
     *
     * @return ModelState
     */
    public function toModelState(): array
    {
        return [
            'minute' => $this->minute,
            'my_team' => $this->myTeam,
            'opponent' => $this->opponent,
            'score' => ['us' => $this->goalsFor, 'them' => $this->goalsAgainst],
            'possession_last_15' => $this->possessionLast15,
            'shots_last_15' => ['us' => $this->shotsLast15For, 'them' => $this->shotsLast15Against],
            'fitness' => ['us' => $this->fitness, 'them' => $this->opponentFitness],
            'current_mentality' => $this->tactics->mentality->value,
            'pressing' => $this->tactics->pressHigh,
            'subs_left' => $this->subsLeft,
        ];
    }
}
