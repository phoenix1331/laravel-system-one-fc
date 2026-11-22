<div>
    <section class="grid items-start gap-10 py-10 lg:grid-cols-[1fr_420px] lg:gap-16 lg:py-16">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-line px-4 py-2 text-sm text-muted">
                <span class="size-2 rounded-full bg-s1" aria-hidden="true"></span>
                Local d3 decision model via Ollama
            </span>

            <h1 class="mt-8 font-display text-[clamp(40px,6vw,68px)] leading-[1.02] font-bold tracking-[-0.03em]">
                Can you beat a System One manager?
            </h1>

            <p class="mt-6 max-w-xl text-lg leading-relaxed text-muted">
                Pick a Welsh club, set your tactics and manage 90 minutes against a decision model running on your own machine.
                It sees what you see. It decides when you decide.
            </p>
        </div>

        <form wire:submit="kickOff" class="card flex flex-col gap-6 p-6 sm:p-8">
            <h2 class="font-display text-xl font-bold">Team selection</h2>

            <div class="flex flex-col gap-3">
                <label for="team" class="label">Your club</label>
                <select id="team" wire:model.live="teamId" class="field">
                    @foreach ($this->teams as $team)
                        <option value="{{ $team->id }}" wire:key="team-{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
                @error('teamId') <p class="text-sm text-s1-text">{{ $message }}</p> @enderror
            </div>

            @if ($this->selectedTeam)
                <dl class="panel flex flex-col gap-3 p-5" aria-label="{{ $this->selectedTeam->name }} ratings">
                    @foreach (['attack' => 'Attack', 'midfield' => 'Midfield', 'defence' => 'Defence', 'fitness' => 'Fitness'] as $rating => $label)
                        <div class="grid grid-cols-[88px_1fr_32px] items-center gap-4" wire:key="rating-{{ $rating }}">
                            <dt class="text-sm text-muted">{{ $label }}</dt>
                            <dd class="bar-track" role="meter" aria-valuemin="1" aria-valuemax="100" aria-valuenow="{{ $this->selectedTeam->{$rating} }}" aria-label="{{ $label }}">
                                <div class="h-full rounded-full bg-you transition-all" style="width: {{ $this->selectedTeam->{$rating} }}%"></div>
                            </dd>
                            <dd class="text-right font-mono text-sm">{{ $this->selectedTeam->{$rating} }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            <div class="flex flex-col gap-3">
                <label for="opponent" class="label">System One manages</label>
                <select id="opponent" wire:model="opponentId" class="field">
                    <option value="">Random opponent</option>
                    @foreach ($this->teams as $team)
                        @continue($team->id === $teamId)
                        <option value="{{ $team->id }}" wire:key="opponent-{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
                @error('opponentId') <p class="text-sm text-s1-text">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                Kick off
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>

            <p class="text-center text-sm text-muted">90 minutes takes about {{ $minutesToPlay }} {{ Str::plural('minute', $minutesToPlay) }} to play</p>
        </form>
    </section>

    <section id="how-it-works" class="border-t border-line py-12">
        <h2 class="font-display text-2xl font-bold">How it works</h2>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['01', 'text-you', 'Pick your club', 'Every club has attack, midfield, defence and fitness ratings. Underdogs can still nick it.'],
                ['02', 'text-you', 'Decide at key moments', 'Play pauses at kick-off, every 15 minutes and after goals. You both choose mentality and pressing, blind.'],
                ['03', 'text-s1', 'See what it was thinking', "At full time, the model's decision log shows every call it made and how sure it was."],
            ] as [$number, $colour, $heading, $body])
                <article class="card" wire:key="how-{{ $number }}">
                    <p class="font-mono text-sm {{ $colour }}">{{ $number }}</p>
                    <h3 class="mt-3 font-display text-lg font-bold">{{ $heading }}</h3>
                    <p class="mt-3 leading-relaxed text-muted">{{ $body }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <footer class="flex flex-col gap-2 border-t border-line pt-6 text-sm text-muted sm:flex-row sm:justify-between">
        <p>Runs locally on Ollama · model <span class="font-mono font-medium text-text">{{ $model }}</span></p>
        <p>Club ratings are invented for the game</p>
    </footer>
</div>
