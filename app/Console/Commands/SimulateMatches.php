<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\Side;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\RuleBasedManager;
use App\Managers\SystemOneManager;
use App\Models\Team;
use App\Simulation\EngineFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[Signature('fc:simulate {count=100 : How many matches to play} {--model= : Model to benchmark, e.g. d3:4b (defaults to SYSTEM_ONE_MODEL)} {--seed=1 : Seed for picking clubs and match seeds, so a benchmark can be repeated}')]
#[Description('Benchmark System One against the rule-based bot over many matches')]
class SimulateMatches extends Command
{
    public function handle(EngineFactory $engines, SystemOneManager $manager): int
    {
        $count = max(1, (int) $this->argument('count'));
        $model = $this->option('model');
        $manager = is_string($model) && $model !== '' ? $manager->withModel($model) : $manager;
        $teams = Team::query()->get();

        if ($teams->count() < 2) {
            $this->components->error('Seed the clubs first: php artisan db:seed');

            return self::FAILURE;
        }

        // Picks clubs and seeds; separate from each match's own Randomizer.
        $draw = new Randomizer(new Mt19937((int) $this->option('seed')));
        $results = ['win' => 0, 'draw' => 0, 'loss' => 0];
        $goalsFor = 0;
        $goalsAgainst = 0;
        $decisions = 0;
        $fallbacks = 0;
        $responseMs = [];

        $this->components->info("System One ({$manager->model()}) v the rule-based bot, {$count} matches");
        $bar = $this->output->createProgressBar($count);

        foreach (range(1, $count) as $match) {
            $picked = $draw->pickArrayKeys($teams->all(), 2);
            $bot = $teams[$picked[0]];
            $systemOne = $teams[$picked[1]];
            $seed = $draw->getInt(1, 2_147_483_647);

            $state = $engines->runner($seed)->play(
                $engines->forSeed($seed)->kickOff($bot->toTeamState(), $systemOne->toTeamState()),
                new RuleBasedManager,
                $manager,
                onDecision: function (Side $side, DecisionContext $context, Decision $decision) use (&$decisions, &$fallbacks, &$responseMs): void {
                    if ($side !== Side::Away) {
                        return;
                    }

                    $decisions++;
                    $fallbacks += $decision->fallbackUsed ? 1 : 0;

                    if ($decision->responseMs !== null) {
                        $responseMs[] = $decision->responseMs;
                    }
                },
            );

            $goalsFor += $state->away->goals;
            $goalsAgainst += $state->home->goals;
            $results[match ($state->away->goals <=> $state->home->goals) {
                1 => 'win',
                0 => 'draw',
                -1 => 'loss',
            }]++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'System One'], [
            ['Won / drawn / lost', "{$results['win']} / {$results['draw']} / {$results['loss']}"],
            ['Goals for / against', "{$goalsFor} / {$goalsAgainst}"],
            ['Goals per match', number_format(($goalsFor + $goalsAgainst) / $count, 2)],
            ['Fallbacks', "{$fallbacks} of {$decisions} decisions"],
            ['Average response', $responseMs === [] ? 'n/a' : number_format(array_sum($responseMs) / count($responseMs) / 1000, 1).'s'],
        ]);

        return self::SUCCESS;
    }
}
