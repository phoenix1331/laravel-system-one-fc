<?php

declare(strict_types=1);

namespace App\Simulation;

use App\Engine\DecisionPoints;
use App\Engine\MatchEngine;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Side;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\Manager;
use Closure;

/**
 * Plays a whole match between two managers, stopping at every decision point to ask both.
 */
final readonly class MatchRunner
{
    public function __construct(
        private MatchEngine $engine,
        private DecisionPoints $decisionPoints = new DecisionPoints,
        private int $maxSubstitutions = 3,
    ) {}

    /**
     * @param  (Closure(list<MatchEvent>, MatchState): void)|null  $onEvents  called after every step
     * @param  (Closure(Side, DecisionContext, Decision, MatchState): void)|null  $onDecision  called after each manager decides
     */
    public function play(
        MatchState $state,
        Manager $home,
        Manager $away,
        ?Closure $onEvents = null,
        ?Closure $onDecision = null,
    ): MatchState {
        $events = [];

        while (! $state->isFinished()) {
            if ($this->decisionPoints->isDue($state, $events)) {
                $this->decide($state, Side::Home, $home, $onDecision);
                $this->decide($state, Side::Away, $away, $onDecision);
            }

            $events = $this->engine->advance($state);
            $onEvents?->__invoke($events, $state);
        }

        return $state;
    }

    /**
     * @param  (Closure(Side, DecisionContext, Decision, MatchState): void)|null  $onDecision
     */
    private function decide(MatchState $state, Side $side, Manager $manager, ?Closure $onDecision): void
    {
        // Both managers see the match as it stood before either changed anything.
        $context = DecisionContext::fromMatch($state, $side, $this->maxSubstitutions);
        $decision = $manager->decide($context);

        $state->team($side)->tactics = $decision->tactics();
        $onDecision?->__invoke($side, $context, $decision, $state);
    }
}
