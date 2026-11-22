@php
    $home = $this->state->home;
    $away = $this->state->away;
    $shotTotal = max(1, $home->shots + $away->shots);
    $concern = $this->lastRevealedModelDecision?->concern;
@endphp

<section class="card" aria-label="Stats">
    <h2 class="font-display text-xl font-bold">Stats</h2>

    <div class="mt-5 flex flex-col gap-5">
        <div>
            <div class="flex justify-between text-sm">
                <span class="font-mono text-you">{{ $possession['human'] }}%</span>
                <span class="text-muted">Possession</span>
                <span class="font-mono text-s1">{{ $possession['model'] }}%</span>
            </div>
            <div class="mt-2 flex h-2 gap-1" aria-hidden="true">
                <span class="rounded-full bg-you" style="width: {{ $possession['human'] }}%"></span>
                <span class="rounded-full bg-s1" style="width: {{ $possession['model'] }}%"></span>
            </div>
        </div>

        <div>
            <div class="flex justify-between text-sm">
                <span class="font-mono text-you">{{ $home->shots }}</span>
                <span class="text-muted">Shots</span>
                <span class="font-mono text-s1">{{ $away->shots }}</span>
            </div>
            <div class="mt-2 flex h-2 gap-1" aria-hidden="true">
                @if ($home->shots + $away->shots === 0)
                    <span class="w-full rounded-full bg-surface2"></span>
                @else
                    <span class="rounded-full bg-you" style="width: {{ 100 * $home->shots / $shotTotal }}%"></span>
                    <span class="rounded-full bg-s1" style="width: {{ 100 * $away->shots / $shotTotal }}%"></span>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <p class="text-sm text-muted">Fitness</p>
            @foreach ([[$home, 'bg-you'], [$away, 'bg-s1']] as [$team, $colour])
                <div class="grid grid-cols-[1fr_110px_28px] items-center gap-3 text-sm" wire:key="fitness-{{ $loop->index }}">
                    <span class="truncate">{{ $team->name }}</span>
                    <span class="bar-track" aria-hidden="true"><span class="block h-full rounded-full {{ $colour }}" style="width: {{ $team->currentFitness() }}%"></span></span>
                    <span class="text-right font-mono">{{ (int) round($team->currentFitness()) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="card" aria-label="On the pitch now">
    <h2 class="font-display text-xl font-bold">On the pitch now</h2>

    <dl class="mt-5 flex flex-col gap-4">
        @foreach ([['You', 'text-you-text', $home], ['System One', 'text-s1-text', $away]] as [$label, $colour, $team])
            <div class="flex justify-between gap-4" wire:key="pitch-{{ $loop->index }}">
                <dt class="{{ $colour }}">{{ $label }}</dt>
                <dd class="text-right">{{ $team->tactics->mentality->label() }} · {{ $team->tactics->pressHigh ? 'pressing' : 'not pressing' }}</dd>
            </div>
        @endforeach
        <div class="flex justify-between border-t border-surface2 pt-4">
            <dt class="text-muted">System One concern</dt>
            <dd class="font-mono">{{ $concern ?? '-' }} / 5</dd>
        </div>
    </dl>
</section>
