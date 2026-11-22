<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * One team during a match: base ratings plus everything that changes as it is played.
 *
 * @phpstan-type TeamStateArray array{
 *     name: string,
 *     ratings: array{attack: int, midfield: int, defence: int, fitness: int},
 *     tactics: array{mentality: string, press_high: bool},
 *     fitness: float,
 *     attack_bonus: int,
 *     defence_bonus: int,
 *     substitutions_used: int,
 *     goals: int,
 *     shots: int,
 *     possession_minutes: int,
 * }
 */
final class TeamState
{
    public function __construct(
        public readonly string $name,
        public readonly TeamRatings $ratings,
        public Tactics $tactics = new Tactics,
        public ?float $fitness = null,
        public int $attackBonus = 0,
        public int $defenceBonus = 0,
        public int $substitutionsUsed = 0,
        public int $goals = 0,
        public int $shots = 0,
        public int $possessionMinutes = 0,
    ) {
        $this->fitness ??= (float) $ratings->fitness;
    }

    public function currentFitness(): float
    {
        return (float) $this->fitness;
    }

    /**
     * 0.7 at zero fitness up to 1.0 at full fitness (with the default config).
     */
    public function fitnessFactor(EngineConfig $config): float
    {
        return $config->fitnessFactorBase + $config->fitnessFactorScale * ($this->currentFitness() / 100);
    }

    /**
     * Base rating (plus substitution bonuses) x fitness factor x tactic modifiers.
     */
    public function effectiveRatings(EngineConfig $config): EffectiveRatings
    {
        $fitness = $this->fitnessFactor($config);
        $mentality = $this->tactics->mentality;
        $press = $this->tactics->pressHigh ? $config->pressMidfield : 1.0;

        return new EffectiveRatings(
            attack: max(1, $this->ratings->attack + $this->attackBonus) * $fitness * $config->attackMultiplier($mentality),
            midfield: $this->ratings->midfield * $fitness * $press,
            defence: max(1, $this->ratings->defence + $this->defenceBonus) * $fitness * $config->defenceMultiplier($mentality),
        );
    }

    /**
     * Fitness lost in one minute, given the current tactics.
     */
    public function fitnessDrain(EngineConfig $config): float
    {
        $drain = $config->fitnessDrainPerMinute;

        if ($this->tactics->pressHigh) {
            $drain += $config->pressDrain;
        }

        if ($this->tactics->mentality === Mentality::AllOutAttack) {
            $drain += $config->allOutAttackDrain;
        }

        return $drain;
    }

    public function drainFitness(EngineConfig $config): void
    {
        $this->fitness = max($config->fitnessFloor, $this->currentFitness() - $this->fitnessDrain($config));
    }

    public function substitutionsLeft(EngineConfig $config): int
    {
        return max(0, $config->maxSubstitutions - $this->substitutionsUsed);
    }

    /**
     * Make a substitution if one is left. Returns false when nothing changed.
     *
     * Fresh legs restores fitness; an extra striker or defender shifts the
     * ratings for the rest of the match.
     */
    public function substitute(Substitution $substitution, EngineConfig $config): bool
    {
        if ($substitution === Substitution::None || $this->substitutionsLeft($config) === 0) {
            return false;
        }

        match ($substitution) {
            Substitution::FreshLegs => $this->fitness = min(100.0, $this->currentFitness() + $config->freshLegsFitness),
            Substitution::ExtraStriker => $this->shiftRatings($config->extraStriker),
            Substitution::ExtraDefender => $this->shiftRatings($config->extraDefender),
        };

        $this->substitutionsUsed++;

        return true;
    }

    /**
     * @param  array{attack: int, defence: int}  $change
     */
    private function shiftRatings(array $change): void
    {
        $this->attackBonus += $change['attack'];
        $this->defenceBonus += $change['defence'];
    }

    /**
     * @return TeamStateArray
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'ratings' => $this->ratings->toArray(),
            'tactics' => $this->tactics->toArray(),
            'fitness' => $this->currentFitness(),
            'attack_bonus' => $this->attackBonus,
            'defence_bonus' => $this->defenceBonus,
            'substitutions_used' => $this->substitutionsUsed,
            'goals' => $this->goals,
            'shots' => $this->shots,
            'possession_minutes' => $this->possessionMinutes,
        ];
    }

    /**
     * @param  TeamStateArray  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            ratings: TeamRatings::fromArray($data['ratings']),
            tactics: Tactics::fromArray($data['tactics']),
            fitness: (float) $data['fitness'],
            attackBonus: $data['attack_bonus'],
            defenceBonus: $data['defence_bonus'],
            substitutionsUsed: $data['substitutions_used'],
            goals: $data['goals'],
            shots: $data['shots'],
            possessionMinutes: $data['possession_minutes'],
        );
    }
}
