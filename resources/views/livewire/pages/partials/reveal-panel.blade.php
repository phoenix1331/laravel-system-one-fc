@php
    $you = $this->humanDecision;
    $s1 = $this->modelDecision;
@endphp

<section class="rounded-[20px] border border-line-strong bg-surface p-6" aria-label="Decisions revealed">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-display text-2xl font-bold">Decisions at {{ $you->minute }}</h2>
        <span class="text-sm text-muted">Both apply when play resumes</span>
    </div>

    @if ($s1 === null)
        <p class="mt-6 inline-flex items-center gap-2 rounded-full bg-inset px-4 py-2 text-sm" role="status">
            <span class="size-2 animate-pulse rounded-full bg-s1" aria-hidden="true"></span>
            You are locked in. System One is thinking...
        </p>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="panel flex flex-col gap-1 p-5">
                <span class="text-sm text-you-text">You</span>
                <span class="font-display text-xl font-bold">{{ $you->mentality->label() }}</span>
                <span class="text-sm text-muted">{{ $you->press_high ? 'Pressing high' : 'Holding shape' }}</span>
            </div>
            <div @class(['panel flex flex-col gap-1 p-5', 'outline-2 outline-dashed outline-line-strong' => $s1->fallback_used])>
                <span class="text-sm text-s1-text">System One</span>
                <span class="font-display text-xl font-bold">{{ $s1->mentality->label() }}</span>
                <span class="text-sm text-muted">
                    {{ $s1->press_high ? 'Pressing high' : 'Holding shape' }}
                    @if (isset($s1->probabilities['press_high']['true']))
                        · <span class="font-mono">{{ number_format($s1->probabilities['press_high']['true'], 2) }}</span>
                    @endif
                </span>
            </div>
        </div>

        <div class="mt-6">
            <p class="label">How sure System One was</p>
            @if ($s1->fallback_used)
                <p class="mt-3 text-sm text-muted">The model did not answer in time, so the rule-based bot chose for it.</p>
            @else
                <div class="mt-3 flex flex-col gap-2">
                    @foreach ($mentalities as $option)
                        @php $p = $s1->probabilities['mentality'][$option->value] ?? 0; @endphp
                        <div class="grid grid-cols-[110px_1fr_44px] items-center gap-3 text-sm" wire:key="sure-{{ $option->value }}">
                            <span @class(['font-semibold' => $option === $s1->mentality, 'text-muted' => $option !== $s1->mentality])>{{ $option->label() }}</span>
                            <span class="bar-track"><span class="block h-full rounded-full bg-s1" style="width: {{ round($p * 100, 1) }}%"></span></span>
                            <span class="text-right font-mono">{{ number_format($p, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <button type="button" wire:click="resume" class="btn-primary mt-6 w-full">Resume match</button>
    @endif
</section>
