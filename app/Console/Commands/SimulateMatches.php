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
use App\Simulation\Tally;
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
        $systemOne = new Tally;
        $baseline = new Tally;
        $decisions = 0;
        $fallbacks = 0;
        $responseMs = [];

        $this->components->info("System One ({$manager->model()}) v the rule-based bot, {$count} matches");
        $bar = $this->output->createProgressBar($count);

        foreach (range(1, $count) as $match) {
            // pickArrayKeys keeps table order, which would always hand the bot the stronger club; shuffle the pair.
            $picked = $draw->shuffleArray($draw->pickArrayKeys($teams->all(), 2));
            $botClub = $teams[$picked[0]];
            $systemOneClub = $teams[$picked[1]];
            $seed = $draw->getInt(1, 2_147_483_647);

            $state = $engines->runner($seed)->play(
                $engines->forSeed($seed)->kickOff($botClub->toTeamState(), $systemOneClub->toTeamState()),
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

            $systemOne->add($state->away->goals, $state->home->goals);

            // The same fixture and seed with the bot in System One's seat: the baseline to beat.
            $replay = $engines->runner($seed)->play(
                $engines->forSeed($seed)->kickOff($botClub->toTeamState(), $systemOneClub->toTeamState()),
                new RuleBasedManager,
                new RuleBasedManager,
            );
            $baseline->add($replay->away->goals, $replay->home->goals);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['', 'System One', 'Bot in its seat'], [
            ['Won / drawn / lost', $systemOne->record(), $baseline->record()],
            ['Points (3 a win)', $systemOne->points(), $baseline->points()],
            ['Goals for / against', $systemOne->goals(), $baseline->goals()],
            ['Fallbacks', "{$fallbacks} of {$decisions} decisions", '-'],
            ['Average response', $responseMs === [] ? 'n/a' : number_format(array_sum($responseMs) / count($responseMs) / 1000, 1).'s', '-'],
        ]);
        $this->line('Same fixtures and seeds in both columns, so the difference is the manager.');

        return self::SUCCESS;
    }
}
