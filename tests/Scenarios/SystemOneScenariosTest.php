<?php

declare(strict_types=1);

/*
 * Sanity checks against a real System One model. They double as a check on the
 * question wording in config/manager.php: if a wording change makes the model
 * defend when it is losing late, these fail.
 *
 * Skipped unless RUN_OLLAMA_SCENARIOS=true; run them with `make scenarios`.
 */

use App\Engine\Mentality;
use App\Engine\Tactics;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\SystemOneManager;

beforeEach(function (): void {
    if (! filter_var(env('RUN_OLLAMA_SCENARIOS', false), FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set RUN_OLLAMA_SCENARIOS=true to run against a live Ollama.');
    }

    // A cold model load can take far longer than a normal decision.
    config(['manager.timeout' => 180]);
});

function scenario(int $minute, int $for, int $against, int $fitness = 65, int $opponentFitness = 65): Decision
{
    $decision = app(SystemOneManager::class)->decide(new DecisionContext(
        minute: $minute,
        clock: "{$minute}'",
        isHalfTime: false,
        myTeam: 'Bala Town',
        opponent: 'Penybont',
        goalsFor: $for,
        goalsAgainst: $against,
        possessionLast15: 50,
        shotsLast15For: 2,
        shotsLast15Against: 2,
        fitness: $fitness,
        opponentFitness: $opponentFitness,
        tactics: new Tactics,
        subsLeft: 3,
    ));

    expect($decision->fallbackUsed)->toBeFalse('The model did not answer; is Ollama running with '.config('manager.model').'?');

    return $decision;
}

/**
 * Probability mass on the defensive options minus the attacking ones: positive leans defensive.
 */
function defensiveLean(Decision $decision): float
{
    $p = $decision->probabilities['mentality'];

    return ($p['park_the_bus'] + $p['defensive']) - ($p['attacking'] + $p['all_out_attack']);
}

it('leans defensive when two up at 88 minutes', function (): void {
    expect(defensiveLean(scenario(88, 2, 0)))->toBeGreaterThan(0);
})->group('ollama');

it('leans attacking when a goal down at 80 minutes', function (): void {
    expect(defensiveLean(scenario(80, 0, 1)))->toBeLessThan(0);
})->group('ollama');

it('does not go all out attack at 0-0 after 5 minutes', function (): void {
    expect(scenario(5, 0, 0)->mentality)->not->toBe(Mentality::AllOutAttack);
})->group('ollama');

it('is more worried losing late than winning late', function (): void {
    expect(scenario(85, 0, 1)->concern)->toBeGreaterThan(scenario(85, 2, 0)->concern);
})->group('ollama');
