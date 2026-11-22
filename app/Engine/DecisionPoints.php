<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * When both managers get to change their tactics: kick-off, set minutes, half time, and the minute after a goal.
 */
final readonly class DecisionPoints
{
    /**
     * @param  list<int>  $minutes  regular-time minutes that trigger a decision once they have been played
     */
    public function __construct(
        private array $minutes = [15, 30, 60, 75],
    ) {}

    /**
     * @param  list<MatchEvent>  $lastEvents  the events from the step just played
     */
    public function isDue(MatchState $state, array $lastEvents = []): bool
    {
        if ($state->phase === Phase::FullTime) {
            return false;
        }

        if ($state->phase === Phase::PreMatch || $state->phase === Phase::HalfTime) {
            return true;
        }

        foreach ($lastEvents as $event) {
            if ($event->type === EventType::Goal) {
                return true;
            }
        }

        return $state->added === 0 && in_array($state->minute, $this->minutes, true);
    }
}
