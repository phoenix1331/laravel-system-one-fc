@php
    use App\Enums\Dugout;

    $human = $game->humanTeam;
    $model = $game->modelTeam;
    $waitingForModel = $game->isDeciding() && $this->modelDecision === null;
@endphp

<div class="flex flex-col gap-6"
    @if ($this->isPlaying()) wire:poll.{{ $pollMs }}ms="advance" @elseif ($waitingForModel) wire:poll.1s @endif
>
    {{-- Scoreboard --}}
    <section class="card p-6 sm:p-8" aria-label="Scoreboard">
        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4">
            <div>
                <span class="inline-block rounded-full bg-you-chip px-3 py-1 text-xs font-semibold text-you-text">You</span>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <x-club-badge :name="$human->name" size="size-10 sm:size-14" />
                    <p class="font-display text-xl font-bold sm:text-3xl">{{ $human->name }}</p>
                    @if ($bigChance === Dugout::Human)
                        <span class="animate-pulse rounded-full bg-you px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-bg">Big chance</span>
                    @endif
                </div>
            </div>

            <div class="text-center">
                <p class="font-mono text-[clamp(40px,6vw,64px)] leading-none font-medium" aria-live="polite">
                    <span class="text-you">{{ $game->human_score }}</span><span class="mx-2 text-line-strong sm:mx-4">-</span><span class="text-s1">{{ $game->model_score }}</span>
                </p>
                <p class="mt-2 font-mono text-sm text-muted">{{ $game->clock }} · {{ $half }}</p>
            </div>

            <div class="text-right">
                <span class="inline-flex items-center gap-2 rounded-full bg-s1-chip px-3 py-1 text-xs font-semibold text-s1-text">
                    <livewire:system-one-status :model="$game->model" />
                    System One
                </span>
                <div class="mt-2 flex flex-wrap items-center justify-end gap-3">
                    @if ($bigChance === Dugout::Model)
                        <span class="animate-pulse rounded-full bg-s1 px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-bg">Big chance</span>
                    @endif
                    <p class="font-display text-xl font-bold sm:text-3xl">{{ $model->name }}</p>
                    <x-club-badge :name="$model->name" size="size-10 sm:size-14" />
                </div>
            </div>
        </div>

        <div class="relative mt-8 h-1.5 rounded-full bg-surface2" role="progressbar" aria-label="Match progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $progress }}">
            <div class="h-full rounded-full bg-line-strong transition-all" style="width: {{ $progress }}%"></div>
            <span class="absolute top-1/2 left-1/2 h-3 w-px -translate-y-1/2 bg-muted" aria-hidden="true"></span>
            @foreach ($goals as $goal)
                <span wire:key="goal-dot-{{ $loop->index }}"
                    class="absolute top-1/2 size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $goal['dugout'] === Dugout::Human ? 'bg-you' : 'bg-s1' }}"
                    style="left: {{ $goal['position'] }}%"
                    title="Goal {{ $goal['minute'] }}"></span>
            @endforeach
        </div>
    </section>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_360px]">
        <div class="flex flex-col gap-6">
            @if ($game->isDeciding() && $this->humanDecision === null)
                @include('livewire.pages.partials.decision-panel')
            @elseif ($game->isDeciding())
                @include('livewire.pages.partials.reveal-panel')
            @elseif ($this->isPlaying())
                <p class="card flex items-center gap-3 py-4 text-muted">
                    <span class="size-2 animate-pulse rounded-full bg-you" aria-hidden="true"></span>
                    Playing. Next decision point at {{ $this->nextDecisionLabel() }} or after a goal.
                </p>
            @else
                <p class="card py-4">
                    Full time. <a href="{{ route('games.result', $game) }}" class="font-semibold underline">See the result</a>
                </p>
            @endif

            {{-- Commentary --}}
            <section class="card" aria-label="Commentary">
                <h2 class="font-display text-xl font-bold">Commentary</h2>
                <ol class="mt-4 divide-y divide-surface2" aria-live="polite">
                    @if ($game->isDeciding())
                        <li class="grid grid-cols-[56px_1fr] gap-4 py-3 text-muted">
                            <span class="font-mono text-sm">{{ $game->clock }}</span>
                            <span>Play paused. Both managers are making changes.</span>
                        </li>
                    @endif
                    @forelse ($this->events as $event)
                        @php
                            $isGoal = $event->type === 'goal';
                            $isModelChange = $event->type === 'decision' && $event->side === Dugout::Model;
                            $minuteColour = match (true) {
                                $isGoal && $event->side === Dugout::Human => 'text-you',
                                $isGoal || $isModelChange => 'text-s1',
                                default => 'text-muted',
                            };
                        @endphp
                        <li wire:key="event-{{ $event->id }}" class="grid grid-cols-[56px_1fr] gap-4 py-3">
                            <span class="font-mono text-sm {{ $minuteColour }}">{{ $event->minute }}</span>
                            <span @class(['font-semibold' => $isGoal])>{{ $event->commentary }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-muted">The teams are in the tunnel.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="flex flex-col gap-6">
            @include('livewire.pages.partials.match-stats')
        </aside>
    </div>
</div>
