<?php

declare(strict_types=1);

namespace App\Simulation;

use App\Engine\EventType;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Side;
use App\Managers\Decision;

/**
 * Turns match events into one-line commentary from fixed templates. No language model involved.
 *
 * The template is picked from the event's minute, not from the match randomiser,
 * so commentary never changes how a seeded match plays out.
 */
final class Commentator
{
    /** @var array<string, list<string>> */
    private const TEMPLATES = [
        'kick_off_1' => [
            ':home get us under way against :away.',
            'We are off. :home kick off against :away.',
        ],
        'kick_off_2' => [
            'The second half is under way.',
            'Back out they come. :away get the second half started.',
        ],
        'shot' => [
            ':team try their luck from distance, but it flies wide.',
            ':team work an opening, but the keeper saves.',
            'Half a chance for :team, blocked at the last moment.',
            ':team break quickly and fire over the bar.',
            'A header from a :team corner, straight at the keeper.',
        ],
        'goal' => [
            'GOAL! :team score. :home :score :away.',
            'GOAL! :team find the net. It is :home :score :away.',
            'GOAL! A clinical finish for :team. :home :score :away.',
        ],
        'half_time' => [
            'Half time: :home :score :away.',
        ],
        'full_time' => [
            'Full time: :home :score :away.',
        ],
    ];

    public function describe(MatchEvent $event, MatchState $state): string
    {
        $key = match ($event->type) {
            EventType::KickOff => 'kick_off_'.($event->payload['half'] ?? 1),
            default => $event->type->value,
        };

        $templates = self::TEMPLATES[$key];
        $template = $templates[crc32($event->minute.$key.$event->side?->value) % count($templates)];

        return strtr($template, [
            ':home' => $state->home->name,
            ':away' => $state->away->name,
            ':team' => $event->side !== null ? $state->team($event->side)->name : '',
            ':score' => (string) ($event->payload['score'] ?? $state->score()),
        ]);
    }

    /**
     * A line for a tactical decision, e.g. "Bala Town go Attacking and press high."
     */
    public function describeDecision(Side $side, Decision $decision, MatchState $state): string
    {
        $press = $decision->pressHigh ? 'and press high' : 'and hold their shape';

        return "{$state->team($side)->name} go {$decision->mentality->label()} {$press}.";
    }
}
