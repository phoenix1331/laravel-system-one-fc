<?php

declare(strict_types=1);

namespace App\Engine;

use LogicException;
use Random\Randomizer;

/**
 * Plays a match one minute at a time.
 *
 * Every random number comes from the injected Randomizer, so a seeded
 * Randomizer replays the same match given the same tactical decisions.
 */
final class MatchEngine
{
    public function __construct(
        private readonly EngineConfig $config,
        private readonly Randomizer $random,
    ) {}

    /**
     * Set up a match ready to kick off, with added time for both halves decided now.
     */
    public function kickOff(TeamState $home, TeamState $away): MatchState
    {
        return new MatchState(
            home: $home,
            away: $away,
            addedTimeFirstHalf: $this->random->getInt($this->config->addedTimeMin, $this->config->addedTimeMax),
            addedTimeSecondHalf: $this->random->getInt($this->config->addedTimeMin, $this->config->addedTimeMax),
        );
    }

    /**
     * Move the match on by one step: usually one minute, or the half-time and full-time whistles.
     *
     * @return list<MatchEvent>
     */
    public function advance(MatchState $state): array
    {
        return match ($state->phase) {
            Phase::PreMatch => $this->startHalf($state, Phase::FirstHalf, 1),
            Phase::FirstHalf => $this->continueHalf($state, 45, $state->addedTimeFirstHalf, Phase::HalfTime),
            Phase::HalfTime => $this->startHalf($state, Phase::SecondHalf, 46),
            Phase::SecondHalf => $this->continueHalf($state, 90, $state->addedTimeSecondHalf, Phase::FullTime),
            Phase::FullTime => throw new LogicException('The match has finished.'),
        };
    }

    public function homePossessionProbability(EffectiveRatings $home, EffectiveRatings $away): float
    {
        return $home->midfield / ($home->midfield + $away->midfield);
    }

    public function shotProbability(EffectiveRatings $attacking, EffectiveRatings $defending): float
    {
        return $this->clamp(
            $this->config->shotBase * $attacking->attack / $defending->defence,
            $this->config->shotMin,
            $this->config->shotMax,
        );
    }

    public function goalProbability(EffectiveRatings $attacking, EffectiveRatings $defending): float
    {
        return $this->clamp(
            $this->config->goalBase * $attacking->attack / $defending->defence,
            $this->config->goalMin,
            $this->config->goalMax,
        );
    }

    /**
     * @return list<MatchEvent>
     */
    private function startHalf(MatchState $state, Phase $half, int $firstMinute): array
    {
        $state->phase = $half;
        $state->minute = $firstMinute;
        $state->added = 0;

        return [
            new MatchEvent(EventType::KickOff, $state->clock(), payload: ['half' => $half === Phase::FirstHalf ? 1 : 2]),
            ...$this->playMinute($state),
        ];
    }

    /**
     * @return list<MatchEvent>
     */
    private function continueHalf(MatchState $state, int $lastMinute, int $addedTime, Phase $whistle): array
    {
        if ($state->minute < $lastMinute) {
            $state->minute++;

            return $this->playMinute($state);
        }

        if ($state->added < $addedTime) {
            $state->added++;

            return $this->playMinute($state);
        }

        $state->phase = $whistle;
        $type = $whistle === Phase::HalfTime ? EventType::HalfTime : EventType::FullTime;

        return [new MatchEvent($type, $state->clock(), payload: ['score' => $state->score()])];
    }

    /**
     * One minute of football: who has the ball, whether they shoot, whether it goes in.
     *
     * @return list<MatchEvent>
     */
    private function playMinute(MatchState $state): array
    {
        $home = $state->home->effectiveRatings($this->config);
        $away = $state->away->effectiveRatings($this->config);

        $side = $this->random->nextFloat() < $this->homePossessionProbability($home, $away) ? Side::Home : Side::Away;
        [$attacking, $defending] = $side === Side::Home ? [$home, $away] : [$away, $home];
        $team = $state->team($side);
        $team->possessionMinutes++;

        $events = [];
        $shot = $this->random->nextFloat() < $this->shotProbability($attacking, $defending);
        $goal = false;

        // A goal is a shot that went in, so it is recorded as one event, not two.
        if ($shot) {
            $team->shots++;
            $goal = $this->random->nextFloat() < $this->goalProbability($attacking, $defending);
        }

        if ($shot && ! $goal) {
            $events[] = new MatchEvent(EventType::Shot, $state->clock(), $side);
        }

        if ($goal) {
            $team->goals++;
            $events[] = new MatchEvent(EventType::Goal, $state->clock(), $side, ['score' => $state->score()]);
        }

        $state->recordMinute($side, $shot, $goal);
        $state->home->drainFitness($this->config);
        $state->away->drainFitness($this->config);

        return $events;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
