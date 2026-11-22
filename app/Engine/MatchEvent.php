<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Something that happened in a minute: a shot, a goal, half time.
 */
final readonly class MatchEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public EventType $type,
        public string $minute,
        public ?Side $side = null,
        public array $payload = [],
    ) {}
}
