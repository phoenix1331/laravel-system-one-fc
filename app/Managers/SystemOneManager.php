<?php

declare(strict_types=1);

namespace App\Managers;

use App\Engine\Mentality;
use App\SystemOne\Answers;
use App\SystemOne\SystemOneClient;
use App\SystemOne\SystemOneException;
use Psr\Log\LoggerInterface;

/**
 * A manager whose decisions come from a System One decision model.
 *
 * The match state goes in as JSON, the questions from config/manager.php go
 * with it, and the answers come back as probabilities. If the model cannot
 * answer in time, or answers in a shape we do not understand, the rule-based
 * manager decides instead and the decision is marked as a fallback.
 */
final readonly class SystemOneManager implements Manager
{
    /**
     * @param  array<string, array<string, mixed>>  $questions  keyed by question id, as in config/manager.php
     */
    public function __construct(
        private SystemOneClient $client,
        private array $questions,
        private LoggerInterface $logger,
        private Manager $fallback = new RuleBasedManager,
    ) {}

    public function decide(DecisionContext $context): Decision
    {
        try {
            return $this->toDecision($this->client->ask($context->toModelState(), $this->questions));
        } catch (SystemOneException $e) {
            $this->logger->warning('System One manager fell back to the rule-based manager.', [
                'minute' => $context->clock,
                'team' => $context->myTeam,
                'model' => $this->client->model(),
                'reason' => $e->getMessage(),
            ]);

            return $this->fallbackDecision($context);
        }
    }

    public function model(): string
    {
        return $this->client->model();
    }

    /**
     * @throws SystemOneException when an answer is missing or names an unknown option
     */
    private function toDecision(Answers $answers): Decision
    {
        $mentality = Mentality::tryFrom($answers->choice('mentality'))
            ?? throw new SystemOneException('The model chose a mentality that does not exist.');

        $pressYes = $answers->noul('press_high');

        return new Decision(
            mentality: $mentality,
            pressHigh: $pressYes >= 0.5,
            probabilities: [
                'mentality' => $answers->probabilities('mentality'),
                'press_high' => ['true' => $pressYes, 'false' => 1 - $pressYes],
                'concern' => $answers->probabilities('concern'),
            ],
            concern: $this->concernLevel($answers->score('concern')),
            responseMs: $answers->responseMs,
        );
    }

    /**
     * The score comes back zero-indexed (0 = calm); the game shows concern from 1 to 5.
     */
    private function concernLevel(float $score): int
    {
        return max(1, min(5, (int) round($score) + 1));
    }

    private function fallbackDecision(DecisionContext $context): Decision
    {
        $decision = $this->fallback->decide($context);

        return new Decision(
            mentality: $decision->mentality,
            pressHigh: $decision->pressHigh,
            substitution: $decision->substitution,
            fallbackUsed: true,
        );
    }
}
