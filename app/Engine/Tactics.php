<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * How a team is set up to play: a mentality and whether it presses high.
 */
final readonly class Tactics
{
    public function __construct(
        public Mentality $mentality = Mentality::Balanced,
        public bool $pressHigh = false,
    ) {}

    /**
     * @return array{mentality: string, press_high: bool}
     */
    public function toArray(): array
    {
        return [
            'mentality' => $this->mentality->value,
            'press_high' => $this->pressHigh,
        ];
    }

    /**
     * @param  array{mentality: string, press_high: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Mentality::from($data['mentality']), $data['press_high']);
    }
}
