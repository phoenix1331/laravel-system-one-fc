<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Engine\Mentality;
use App\Engine\Side;
use App\Enums\Dugout;
use App\Games\GameRunner;
use App\Games\Verdict;
use App\Models\Game;
use App\Models\GameDecision;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The result, the stats and the model's decision log with its probabilities.
 *
 * @phpstan-type LogRow array{
 *     minute: string,
 *     score: string,
 *     you: GameDecision|null,
 *     model: GameDecision,
 *     confidence: float|null,
 *     runnerUp: array{label: string, probability: float}|null,
 * }
 */
#[Layout('components.layouts.app')]
class FullTime extends Component
{
    public Game $game;

    public function mount(): void
    {
        if (! $this->game->isFinished()) {
            $this->redirectRoute('games.show', $this->game, navigate: false);
        }
    }

    /**
     * Same clubs, same model, a fresh seed.
     */
    public function rematch(GameRunner $runner): void
    {
        $game = $runner->start($this->game->humanTeam, $this->game->modelTeam, $this->game->model);

        $this->redirectRoute('games.show', $game, navigate: false);
    }

    public function render(): View
    {
        $state = $this->game->state();
        $possession = $state->home->possessionMinutes + $state->away->possessionMinutes;
        $human = $possession > 0 ? (int) round(100 * $state->team(Side::Home)->possessionMinutes / $possession) : 50;

        return view('livewire.pages.full-time', [
            'verdict' => new Verdict($this->game),
            'state' => $state,
            'possession' => ['human' => $human, 'model' => 100 - $human],
            'averageResponse' => $this->averageResponseSeconds(),
            'log' => $this->decisionLog(),
        ])
            ->title('Full time')
            ->layoutData(['context' => "Model {$this->game->model} · seed {$this->game->seed}"]);
    }

    /**
     * Average time the model took to answer, ignoring decisions where it never did.
     */
    private function averageResponseSeconds(): ?float
    {
        $average = $this->game->decisions()
            ->where('side', Dugout::Model)
            ->where('fallback_used', false)
            ->avg('response_ms');

        return $average !== null ? round((float) $average / 1000, 1) : null;
    }

    /**
     * One row per decision point: both choices, how sure the model was, and its runner-up.
     *
     * @return list<LogRow>
     */
    private function decisionLog(): array
    {
        $decisions = $this->game->decisions()->orderBy('decision_point')->get()->groupBy('decision_point');
        $rows = [];

        foreach ($decisions as $pair) {
            $you = $pair->firstWhere('side', Dugout::Human);
            $model = $pair->firstWhere('side', Dugout::Model);

            if ($model === null) {
                continue;
            }

            $probabilities = $model->probabilities['mentality'] ?? [];
            arsort($probabilities);
            unset($probabilities[$model->mentality->value]);
            $runnerUpKey = array_key_first($probabilities);

            $rows[] = [
                'minute' => $model->minute,
                // Scores are stored from each side's view; show them home first, as on the scoreboard.
                'score' => $you !== null ? $you->score : implode('-', array_reverse(explode('-', $model->score))),
                'you' => $you,
                'model' => $model,
                'confidence' => $model->fallback_used ? null : ($model->probabilities['mentality'][$model->mentality->value] ?? null),
                'runnerUp' => $runnerUpKey !== null && ! $model->fallback_used ? [
                    'label' => Mentality::from((string) $runnerUpKey)->label(),
                    'probability' => $probabilities[$runnerUpKey],
                ] : null,
            ];
        }

        return $rows;
    }
}
