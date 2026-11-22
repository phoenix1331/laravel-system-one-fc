<div class="flex flex-col gap-6">
    {{-- Result --}}
    <section class="card px-6 py-10 text-center sm:px-10" aria-label="Result">
        <p class="font-mono text-sm tracking-[0.2em] text-muted">FULL TIME</p>

        <div class="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2">
            <span class="font-display text-2xl font-bold sm:text-3xl">{{ $game->humanTeam->name }}</span>
            <span class="font-mono text-[clamp(56px,9vw,84px)] leading-none font-medium">
                <span class="text-you">{{ $game->human_score }}</span><span class="mx-3 text-line-strong">-</span><span class="text-s1">{{ $game->model_score }}</span>
            </span>
            <span class="font-display text-2xl font-bold sm:text-3xl">{{ $game->modelTeam->name }}</span>
        </div>

        <h1 class="mt-6 font-display text-3xl font-bold tracking-tight sm:text-4xl">{{ $verdict->headline() }}</h1>
        <p class="mx-auto mt-4 max-w-xl text-muted">{{ $verdict->goals() }}</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <button type="button" wire:click="rematch" class="btn-primary" wire:loading.attr="disabled">Rematch</button>
            <a href="{{ route('home') }}" class="btn-secondary">Pick another club</a>
        </div>
    </section>

    {{-- Stat tiles --}}
    <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Match stats">
        <div class="card p-5">
            <p class="text-sm text-muted">Possession</p>
            <p class="mt-2 font-mono text-2xl"><span class="text-you">{{ $possession['human'] }}%</span> <span class="text-muted">/</span> <span class="text-s1">{{ $possession['model'] }}%</span></p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-muted">Shots</p>
            <p class="mt-2 font-mono text-2xl"><span class="text-you">{{ $state->home->shots }}</span> <span class="text-muted">/</span> <span class="text-s1">{{ $state->away->shots }}</span></p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-muted">Final fitness</p>
            <p class="mt-2 font-mono text-2xl"><span class="text-you">{{ (int) round($state->home->currentFitness()) }}</span> <span class="text-muted">/</span> <span class="text-s1">{{ (int) round($state->away->currentFitness()) }}</span></p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-muted">Model response</p>
            <p class="mt-2 font-mono text-2xl">
                @if ($averageResponse !== null)
                    {{ number_format($averageResponse, 1) }}s <span class="text-sm text-muted">avg</span>
                @else
                    <span class="text-muted">n/a</span>
                @endif
            </p>
        </div>
    </section>

    {{-- Decision log --}}
    <section class="card" aria-label="Inside the System One dugout">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-bold">Inside the System One dugout</h2>
                <p class="mt-1 text-muted">Every call it made, how sure it was, and the runner-up option.</p>
            </div>
            <p class="flex items-center gap-2 text-sm text-muted">
                <span class="size-4 rounded border border-dashed border-muted" aria-hidden="true"></span>
                Rule-based fallback used
            </p>
        </div>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[860px] text-left">
                <thead class="text-sm text-muted">
                    <tr class="border-b border-surface2">
                        <th scope="col" class="py-3 pr-4 font-medium">Min</th>
                        <th scope="col" class="py-3 pr-4 font-medium">Score</th>
                        <th scope="col" class="py-3 pr-4 font-medium">You</th>
                        <th scope="col" class="py-3 pr-4 font-medium">System One</th>
                        <th scope="col" class="py-3 pr-4 font-medium">Subs</th>
                        <th scope="col" class="py-3 pr-4 font-medium">Confidence</th>
                        <th scope="col" class="py-3 font-medium">Concern</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface2">
                    @forelse ($log as $row)
                        <tr wire:key="log-{{ $loop->index }}">
                            <td class="py-4 pr-4 font-mono text-sm text-muted">{{ $row['minute'] }}</td>
                            <td class="py-4 pr-4 font-mono">{{ str_replace('-', ' - ', $row['score']) }}</td>
                            <td class="py-4 pr-4 text-you-text">{{ $row['you']?->mentality->label() ?? '-' }}</td>
                            <td class="py-4 pr-4">
                                <span @class(['font-semibold', 'rounded-md border border-dashed border-muted px-2 py-1' => $row['model']->fallback_used])>
                                    {{ $row['model']->mentality->label() }}
                                </span>
                            </td>
                            <td class="py-4 pr-4 text-sm">
                                @if ($row['you']?->substitution)
                                    <span class="block text-you-text">{{ $row['you']->substitution->label() }}</span>
                                @endif
                                @if ($row['model']->substitution)
                                    <span class="block text-s1-text">{{ $row['model']->substitution->label() }}</span>
                                @endif
                                @if (! $row['you']?->substitution && ! $row['model']->substitution)
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="py-4 pr-4">
                                <div class="grid grid-cols-[1fr_44px] items-center gap-3">
                                    <span class="bar-track"><span class="block h-full rounded-full bg-s1" style="width: {{ round(($row['confidence'] ?? 0) * 100, 1) }}%"></span></span>
                                    <span class="text-right font-mono text-sm">{{ $row['confidence'] !== null ? number_format($row['confidence'], 2) : 'n/a' }}</span>
                                </div>
                                <p class="mt-1 text-sm text-muted">
                                    @if ($row['model']->fallback_used)
                                        Model timed out, bot chose
                                    @elseif ($row['runnerUp'])
                                        Next: {{ $row['runnerUp']['label'] }} {{ number_format($row['runnerUp']['probability'], 2) }}
                                    @endif
                                </p>
                            </td>
                            <td class="py-4">
                                @php $concern = $row['model']->concern ?? 0; @endphp
                                <span class="flex gap-1" role="img" aria-label="{{ $row['model']->concern !== null ? "Concern {$concern} of 5" : 'No concern reading' }}">
                                    @foreach (range(1, 5) as $dot)
                                        <span @class(['size-2 rounded-full', 'bg-s1' => $dot <= $concern, 'bg-surface2' => $dot > $concern])></span>
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-muted">No decisions were recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
