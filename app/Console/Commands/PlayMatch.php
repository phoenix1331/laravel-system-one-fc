<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engine\EventType;
use App\Engine\MatchEvent;
use App\Engine\MatchState;
use App\Engine\Side;
use App\Managers\Decision;
use App\Managers\DecisionContext;
use App\Managers\Manager;
use App\Managers\RuleBasedManager;
use App\Managers\SystemOneManager;
use App\Models\Team;
use App\Simulation\Commentator;
use App\Simulation\EngineFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fc:play {home : Home club name} {away : Away club name} {--seed= : Replay a match from its seed} {--model= : Let System One manage the away side, optionally naming the model, e.g. --model=d3:4b}')]
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

        $awayManager = $this->awayManager();
        $awayLabel = $awayManager instanceof SystemOneManager ? "System One ({$awayManager->model()})" : 'rules';

        $this->components->info("{$home->name} (rules) v {$away->name} ({$awayLabel}), seed {$seed}");

        $runner = $engines->runner($seed);
        $state = $engines->forSeed($seed)->kickOff($home->toTeamState(), $away->toTeamState());

        $runner->play(
            $state,
            new RuleBasedManager,
            $awayManager,
            onEvents: function (array $events, MatchState $state) use ($commentator): void {
                foreach ($events as $event) {
                    $this->printEvent($event, $state, $commentator);
                }
            },
            onDecision: function (Side $side, DecisionContext $context, Decision $decision, MatchState $state) use ($commentator, $awayManager): void {
                $isModel = $side === Side::Away && $awayManager instanceof SystemOneManager;

                // Bot decisions are only reported at kick-off or when they change something; the model's always are.
                if (! $isModel && $context->minute > 0 && $decision->tactics() == $context->tactics) {
                    return;
                }

                $line = sprintf('%6s  %s', $context->clock, $commentator->describeDecision($side, $decision, $state));

                if (! $isModel) {
                    $this->line("  <fg=gray>{$line}</>");

                    return;
                }

                $this->line("  <fg=yellow>{$line}</>");
                $this->line('          <fg=yellow>'.$this->modelWorking($decision).'</>');
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

    /**
     * --model on its own uses the configured model; --model=d3:4b picks another.
     */
    private function awayManager(): Manager
    {
        if (! $this->input->hasParameterOption('--model')) {
            return new RuleBasedManager;
        }

        $model = $this->option('model');

        if (is_string($model) && $model !== '') {
            config(['manager.model' => $model]);
        }

        return app(SystemOneManager::class);
    }

    /**
     * E.g. "ATTACKING 0.58, all out attack 0.27 | press 0.73 | concern 3/5 | 12.4s"
     */
    private function modelWorking(Decision $decision): string
    {
        if ($decision->fallbackUsed) {
            return '[fallback] the model did not answer, so the rule-based bot chose';
        }

        $mentalities = [];

        foreach ($decision->topMentalities() as $key => $probability) {
            $label = $key === $decision->mentality->value ? strtoupper(str_replace('_', ' ', $key)) : str_replace('_', ' ', $key);
            $mentalities[] = sprintf('%s %.2f', $label, $probability);
        }

        return sprintf(
            '%s | press %.2f | concern %d/5 | %.1fs',
            implode(', ', $mentalities),
            $decision->probabilities['press_high']['true'] ?? 0,
            $decision->concern ?? 0,
            ($decision->responseMs ?? 0) / 1000,
        );
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
