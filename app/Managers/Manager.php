<?php

declare(strict_types=1);

namespace App\Managers;

/**
 * Anything that can pick tactics: a person, a set of rules, or a decision model.
 */
interface Manager
{
    public function decide(DecisionContext $context): Decision;
}
