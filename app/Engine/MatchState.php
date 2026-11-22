<?php

declare(strict_types=1);

namespace App\Engine;

/**
 * Everything about a match in progress. Serialises to an array so it can be stored between requests.
 *
 * @phpstan-import-type TeamStateArray from TeamState
 *
 * @phpstan-type MinuteRecord array{minute: string, possession: string, shot: bool, goal: bool}
 * @phpstan-type MatchStateArray array{
 *     home: TeamStateArray,
 *     away: TeamStateArray,
 *     phase: string,
 *     minute: int,
 *     added: int,
 *     added_time: array{first: int, second: int},
 *     recent: list<MinuteRecord>,
 * }
 */
final class MatchState
{
    /** Number of minutes kept for the "last 15 minutes" stats. */
    public const RECENT_WINDOW = 15;

    /**
     * @param  list<MinuteRecord>  $recent
     */
    public function __construct(
        public readonly TeamState $home,
        public readonly TeamState $away,
        public Phase $phase = Phase::PreMatch,
        public int $minute = 0,
        public int $added = 0,
        public int $addedTimeFirstHalf = 0,
        public int $addedTimeSecondHalf = 0,
        public array $recent = [],
    ) {}

    public function team(Side $side): TeamState
    {
        return $side === Side::Home ? $this->home : $this->away;
    }

    public function isFinished(): bool
    {
        return $this->phase === Phase::FullTime;
    }

    /**
     * The match clock as shown to people: 0', 72', 45+2', 90+3'.
     */
    public function clock(): string
    {
        return $this->added > 0 ? "{$this->minute}+{$this->added}'" : "{$this->minute}'";
    }

    public function score(): string
    {
        return "{$this->home->goals}-{$this->away->goals}";
    }

    public function recordMinute(Side $possession, bool $shot, bool $goal): void
    {
        $this->recent[] = [
            'minute' => $this->clock(),
            'possession' => $possession->value,
            'shot' => $shot,
            'goal' => $goal,
        ];

        $this->recent = array_slice($this->recent, -self::RECENT_WINDOW);
    }

    /**
     * Possession percentage for a side over the recent window; 50 before a ball is kicked.
     */
    public function possessionLast15(Side $side): int
    {
        if ($this->recent === []) {
            return 50;
        }

        $minutes = count(array_filter($this->recent, fn (array $record): bool => $record['possession'] === $side->value));

        return (int) round(100 * $minutes / count($this->recent));
    }

    public function shotsLast15(Side $side): int
    {
        return count(array_filter(
            $this->recent,
            fn (array $record): bool => $record['shot'] && $record['possession'] === $side->value,
        ));
    }

    /**
     * @return MatchStateArray
     */
    public function toArray(): array
    {
        return [
            'home' => $this->home->toArray(),
            'away' => $this->away->toArray(),
            'phase' => $this->phase->value,
            'minute' => $this->minute,
            'added' => $this->added,
            'added_time' => ['first' => $this->addedTimeFirstHalf, 'second' => $this->addedTimeSecondHalf],
            'recent' => $this->recent,
        ];
    }

    /**
     * @param  MatchStateArray  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            home: TeamState::fromArray($data['home']),
            away: TeamState::fromArray($data['away']),
            phase: Phase::from($data['phase']),
            minute: $data['minute'],
            added: $data['added'],
            addedTimeFirstHalf: $data['added_time']['first'],
            addedTimeSecondHalf: $data['added_time']['second'],
            recent: $data['recent'],
        );
    }
}
