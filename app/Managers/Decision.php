<?php

declare(strict_types=1);

namespace App\Managers;

use App\Engine\Mentality;
use App\Engine\Substitution;
use App\Engine\Tactics;

/**
 * What a manager chose at a decision point, plus the model's working when a model chose it.
 */
final readonly class Decision
{
    /**
     * @param  array<string, array<string, float>>|null  $probabilities  raw model output per question, null for non-model managers
     * @param  int|null  $concern  1 (calm) to 5 (very worried), model only
     * @param  int|null  $responseMs  how long the model took to answer
     */
    public function __construct(
        public Mentality $mentality,
        public bool $pressHigh = false,
        public ?Substitution $substitution = null,
        public ?array $probabilities = null,
        public ?int $concern = null,
        public bool $fallbackUsed = false,
        public ?int $responseMs = null,
    ) {}

    public static function fromTactics(Tactics $tactics): self
    {
        return new self($tactics->mentality, $tactics->pressHigh);
    }

    public function tactics(): Tactics
    {
        return new Tactics($this->mentality, $this->pressHigh);
    }
}
