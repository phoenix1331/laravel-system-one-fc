<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\EventType;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Side;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\RuleBasedManager;
use App\Models\Team;
use App\Simulation\Commentator;
use App\Simulation\EngineFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fc:play {home : Home club name} {away : Away club name} {--seed= : Replay a match from its seed}')]
#[Description('Play a full match in the terminal between two managers')]
class PlayMatch extends Command
{
    public function handle(EngineFactory $engines, Commentator $commentator): int
    {
        $home = $this->findTeam((string) $this->argument('home'));
        $away = $this->findTeam((string) $this->argument('away'));

        if ($home === null || $away === null) {
            return self::FAILURE;
        }

        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : random_int(1, PHP_INT_MAX);

        $this->components->info("{$home->name} v {$away->name}, seed {$seed}");

        $runner = $engines->runner($seed);
        $state = $engines->forSeed($seed)->kickOff($home->toTeamState(), $away->toTeamState());

        $runner->play(
            $state,
            new RuleBasedManager,
            new RuleBasedManager,
            onEvents: function (array $events, MatchState $state) use ($commentator): void {
                foreach ($events as $event) {
                    $this->printEvent($event, $state, $commentator);
                }
            },
            onDecision: function (Side $side, DecisionContext $context, Decision $decision, MatchState $state) use ($commentator): void {
                // Only report a decision at kick-off or when it changes something.
                if ($context->minute > 0 && $decision->tactics() == $context->tactics) {
                    return;
                }

                $this->line(sprintf('  <fg=gray>%6s  %s</>', $context->clock, $commentator->describeDecision($side, $decision, $state)));
            },
        );

        $this->newLine();
        $this->table(['', $state->home->name, $state->away->name], [
            ['Goals', $state->home->goals, $state->away->goals],
            ['Shots', $state->home->shots, $state->away->shots],
            ['Possession', $this->possession($state, Side::Home).'%', $this->possession($state, Side::Away).'%'],
            ['Fitness', (int) round($state->home->currentFitness()), (int) round($state->away->currentFitness())],
        ]);
        $this->line("Replay this match with --seed={$seed}");

        return self::SUCCESS;
    }

    private function findTeam(string $name): ?Team
    {
        $team = Team::query()->where('name', $name)->first()
            ?? Team::query()->where('name', 'like', "%{$name}%")->first();

        if ($team === null) {
            $this->components->error("No club called \"{$name}\". Try: ".Team::query()->orderBy('name')->pluck('name')->join(', '));
        }

        return $team;
    }

    private function printEvent(MatchEvent $event, MatchState $state, Commentator $commentator): void
    {
        $line = sprintf('%6s  %s', $event->minute, $commentator->describe($event, $state));

        $this->line($event->type === EventType::Goal ? "<options=bold>{$line}</>" : $line);
    }

    private function possession(MatchState $state, Side $side): int
    {
        $total = $state->home->possessionMinutes + $state->away->possessionMinutes;

        return (int) round(100 * $state->team($side)->possessionMinutes / max(1, $total));
    }
}
