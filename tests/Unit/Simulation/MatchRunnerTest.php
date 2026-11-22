<?php

declare(strict_types=1);

use App\Engine\Mentality;
use App\Engine\Side;
use App\Engine\Substitution;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\Manager;
use App\Managers\RuleBasedManager;
use App\Simulation\MatchRunner;

/**
 * A manager that always picks the same thing and remembers when it was asked.
 */
final class FixedManager implements Manager
{
    /** @var list<DecisionContext> */
    public array $asked = [];

    public function __construct(private readonly Decision $decision) {}

    public function decide(DecisionContext $context): Decision
    {
        $this->asked[] = $context;

        return $this->decision;
    }
}

it('asks both managers at every decision point and applies their tactics', function (): void {
    $noGoals = ['goal' => ['base' => 0.0, 'min' => 0.0, 'max' => 0.0]];
    $engine = seededEngine(4, $noGoals);
    $home = new FixedManager(new Decision(Mentality::Attacking, true));
    $away = new FixedManager(new Decision(Mentality::Defensive));

    $state = (new MatchRunner($engine, engineConfig()))->play($engine->kickOff(evenTeam('Home'), evenTeam('Away')), $home, $away);

    expect($state->isFinished())->toBeTrue()
        ->and($home->asked)->toHaveCount(6)
        ->and($away->asked)->toHaveCount(6)
        ->and($home->asked[0]->minute)->toBe(0)
        ->and($away->asked[0]->myTeam)->toBe('Away')
        ->and($state->home->tactics->mentality)->toBe(Mentality::Attacking)
        ->and($state->home->tactics->pressHigh)->toBeTrue()
        ->and($state->away->tactics->mentality)->toBe(Mentality::Defensive);
});

it('reports every step and every decision through the callbacks', function (): void {
    $engine = seededEngine(8);
    $steps = 0;
    $decisions = [];

    (new MatchRunner($engine, engineConfig()))->play(
        $engine->kickOff(evenTeam(), evenTeam()),
        new RuleBasedManager,
        new RuleBasedManager,
        onEvents: function () use (&$steps): void {
            $steps++;
        },
        onDecision: function (Side $side) use (&$decisions): void {
            $decisions[] = $side;
        },
    );

    // 90 minutes, added time, plus the half-time and full-time whistles.
    expect($steps)->toBeGreaterThanOrEqual(92)
        ->and($decisions[0])->toBe(Side::Home)
        ->and($decisions[1])->toBe(Side::Away)
        ->and(count($decisions) % 2)->toBe(0);
});

it('applies substitutions and stops at three', function (): void {
    $engine = seededEngine(4);
    $home = new FixedManager(new Decision(Mentality::Balanced, substitution: Substitution::ExtraStriker));

    $state = (new MatchRunner($engine, engineConfig()))->play($engine->kickOff(evenTeam(), evenTeam()), $home, new RuleBasedManager);

    expect($state->home->substitutionsUsed)->toBe(3)
        ->and($state->home->attackBonus)->toBe(24)
        ->and($home->asked[3]->subsLeft)->toBe(0);
});
