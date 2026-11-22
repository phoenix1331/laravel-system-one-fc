<?php

declare(strict_types=1);

use App\Engine\EventType;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Mentality;
use App\Engine\Side;
use App\Managers\Decision;
use App\Simulation\Commentator;

function commentaryMatch(): MatchState
{
    $state = new MatchState(evenTeam('Bala Town'), evenTeam('Penybont'));
    $state->home->goals = 2;
    $state->away->goals = 1;

    return $state;
}

it('names the scoring team and the score in a goal line', function (): void {
    $line = (new Commentator)->describe(new MatchEvent(EventType::Goal, "67'", Side::Away, ['score' => '2-1']), commentaryMatch());

    expect($line)->toStartWith('GOAL!')
        ->toContain('Penybont')
        ->toContain('Bala Town 2-1 Penybont');
});

it('describes every kind of event', function (MatchEvent $event, string $expected): void {
    expect((new Commentator)->describe($event, commentaryMatch()))->toContain($expected);
})->with([
    'first half kick-off' => [new MatchEvent(EventType::KickOff, "1'", payload: ['half' => 1]), 'Bala Town'],
    'second half kick-off' => [new MatchEvent(EventType::KickOff, "46'", payload: ['half' => 2]), 'second half'],
    'shot' => [new MatchEvent(EventType::Shot, "12'", Side::Home), 'Bala Town'],
    'half time' => [new MatchEvent(EventType::HalfTime, "45+1'", payload: ['score' => '1-0']), 'Half time: Bala Town 1-0 Penybont'],
    'full time' => [new MatchEvent(EventType::FullTime, "90+3'"), 'Full time: Bala Town 2-1 Penybont'],
]);

it('always gives the same line for the same event', function (): void {
    $event = new MatchEvent(EventType::Shot, "33'", Side::Away);

    expect((new Commentator)->describe($event, commentaryMatch()))
        ->toBe((new Commentator)->describe($event, commentaryMatch()));
});

it('describes a tactical change', function (): void {
    $commentator = new Commentator;

    expect($commentator->describeDecision(Side::Home, new Decision(Mentality::Attacking, true), commentaryMatch()))
        ->toBe('Bala Town go Attacking and press high.')
        ->and($commentator->describeDecision(Side::Away, new Decision(Mentality::ParkTheBus), commentaryMatch()))
        ->toBe('Penybont go Park the bus and hold their shape.');
});
