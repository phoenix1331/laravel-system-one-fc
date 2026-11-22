<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Typed view of config/engine.php, so the engine never touches Laravel config.
 *
 * @phpstan-type MentalityModifiers array<string, array{attack: float, defence: float}>
 * @phpstan-type RatingChange array{attack: int, defence: int}
 * @phpstan-type EngineConfigArray array{
 *     fitness: array{factor_base: float, factor_scale: float, drain_per_minute: float, press_drain: float, all_out_attack_drain: float, floor: int|float},
 *     shot: array{base: float, min: float, max: float},
 *     goal: array{base: float, min: float, max: float},
 *     press: array{midfield: float},
 *     mentality: MentalityModifiers,
 *     added_time: array{min: int, max: int},
 *     substitutions: array{max: int, fresh_legs_fitness: int, extra_striker: RatingChange, extra_defender: RatingChange},
 * }
 */
final readonly class EngineConfig
{
    /**
     * @param  MentalityModifiers  $mentality
     * @param  RatingChange  $extraStriker
     * @param  RatingChange  $extraDefender
     */
    public function __construct(
        public float $fitnessFactorBase,
        public float $fitnessFactorScale,
        public float $fitnessDrainPerMinute,
        public float $pressDrain,
        public float $allOutAttackDrain,
        public float $fitnessFloor,
        public float $shotBase,
        public float $shotMin,
        public float $shotMax,
        public float $goalBase,
        public float $goalMin,
        public float $goalMax,
        public float $pressMidfield,
        public array $mentality,
        public int $addedTimeMin,
        public int $addedTimeMax,
        public int $maxSubstitutions,
        public int $freshLegsFitness,
        public array $extraStriker,
        public array $extraDefender,
    ) {}

    /**
     * @param  EngineConfigArray  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            fitnessFactorBase: (float) $config['fitness']['factor_base'],
            fitnessFactorScale: (float) $config['fitness']['factor_scale'],
            fitnessDrainPerMinute: (float) $config['fitness']['drain_per_minute'],
            pressDrain: (float) $config['fitness']['press_drain'],
            allOutAttackDrain: (float) $config['fitness']['all_out_attack_drain'],
            fitnessFloor: (float) $config['fitness']['floor'],
            shotBase: (float) $config['shot']['base'],
            shotMin: (float) $config['shot']['min'],
            shotMax: (float) $config['shot']['max'],
            goalBase: (float) $config['goal']['base'],
            goalMin: (float) $config['goal']['min'],
            goalMax: (float) $config['goal']['max'],
            pressMidfield: (float) $config['press']['midfield'],
            mentality: $config['mentality'],
            addedTimeMin: $config['added_time']['min'],
            addedTimeMax: $config['added_time']['max'],
            maxSubstitutions: $config['substitutions']['max'],
            freshLegsFitness: $config['substitutions']['fresh_legs_fitness'],
            extraStriker: $config['substitutions']['extra_striker'],
            extraDefender: $config['substitutions']['extra_defender'],
        );
    }

    public function attackMultiplier(Mentality $mentality): float
    {
        return (float) $this->mentality[$mentality->value]['attack'];
    }

    public function defenceMultiplier(Mentality $mentality): float
    {
        return (float) $this->mentality[$mentality->value]['defence'];
    }
}
