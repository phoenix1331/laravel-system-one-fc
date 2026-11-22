<?php

declare(strict_types=1);

namespace App\Managers;

use App\Engine\Mentality;
use App\Engine\Substitution;

/**
 * A manager made of a few fixed rules of thumb.
 *
 * It is the opponent before the model is wired in, the baseline for benchmarks,
 * and the fallback whenever the model cannot answer.
 */
final class RuleBasedManager implements Manager
{
    public function decide(DecisionContext $context): Decision
    {
        return new Decision(
            mentality: $this->mentality($context),
            pressHigh: $this->shouldPress($context),
            substitution: $this->substitution($context),
        );
    }

    private function mentality(DecisionContext $context): Mentality
    {
        $difference = $context->goalDifference();
        $minute = $context->minute;

        // Chasing the game late: throw everyone forward.
        if ($difference < 0 && ($minute >= 80 || ($minute >= 70 && $difference <= -2))) {
            return Mentality::AllOutAttack;
        }

        if ($difference < 0 && $minute >= 60) {
            return Mentality::Attacking;
        }

        // Protecting a narrow lead at the end.
        if ($difference === 1 && $minute >= 80) {
            return Mentality::ParkTheBus;
        }

        if ($difference >= 2 && $minute >= 75) {
            return Mentality::Defensive;
        }

        return Mentality::Balanced;
    }

    /**
     * Change personnel when the game calls for it: legs when tired, a striker when chasing, a defender when protecting.
     */
    private function substitution(DecisionContext $context): ?Substitution
    {
        $difference = $context->goalDifference();

        return match (true) {
            $context->subsLeft === 0 => null,
            $difference < 0 && $context->minute >= 70 => Substitution::ExtraStriker,
            $difference === 1 && $context->minute >= 75 => Substitution::ExtraDefender,
            $context->fitness < 50 && $context->minute >= 55 => Substitution::FreshLegs,
            default => null,
        };
    }

    /**
     * Press when clearly fitter than the opponent and not running on empty.
     */
    private function shouldPress(DecisionContext $context): bool
    {
        return $context->fitness >= 40 && $context->fitness - $context->opponentFitness >= 5;
    }
}
