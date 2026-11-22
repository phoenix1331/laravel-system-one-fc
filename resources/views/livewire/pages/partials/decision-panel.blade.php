<section class="rounded-[20px] border border-line-strong bg-surface p-6" aria-label="Your decision">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm text-muted">Play paused at {{ $game->clock }}</p>
            <h2 class="mt-1 font-display text-2xl font-bold">Your call, gaffer</h2>
        </div>
        <p class="inline-flex items-center gap-2 self-start rounded-full bg-inset px-4 py-2 text-sm" role="status">
            <span @class(['size-2 rounded-full bg-s1', 'animate-pulse' => $this->modelDecision === null]) aria-hidden="true"></span>
            {{ $this->modelDecision ? 'System One has decided. Hidden until you lock in.' : 'System One is thinking...' }}
        </p>
    </div>

    <form wire:submit="lockIn" class="mt-6 flex flex-col gap-6">
        <fieldset>
            <legend class="label">Mentality</legend>
            <div class="panel mt-3 grid grid-cols-2 gap-1 p-1.5 sm:grid-cols-5" role="group" aria-label="Mentality">
                @foreach ($mentalities as $option)
                    <button type="button"
                        wire:key="mentality-{{ $option->value }}"
                        x-on:click="$wire.mentality = '{{ $option->value }}'"
                        x-bind:aria-pressed="$wire.mentality === '{{ $option->value }}' ? 'true' : 'false'"
                        x-bind:class="$wire.mentality === '{{ $option->value }}' ? 'bg-text text-bg' : 'text-muted hover:text-text'"
                        aria-pressed="{{ $mentality === $option->value ? 'true' : 'false' }}"
                        class="h-11 rounded-[10px] text-sm font-semibold transition"
                    >{{ $option->shortLabel() }}</button>
                @endforeach
            </div>
            @foreach ($mentalities as $option)
                <p x-show="$wire.mentality === '{{ $option->value }}'" @if ($mentality !== $option->value) x-cloak @endif class="mt-3 text-sm text-muted" wire:key="mentality-help-{{ $option->value }}">
                    {{ $option->description() }}
                </p>
            @endforeach
        </fieldset>

        <div class="flex flex-col gap-3">
            <label for="substitution" class="label">Substitution · {{ $subsLeft }} of 3 left</label>
            <select id="substitution" wire:model="substitution" class="field" @disabled($subsLeft === 0)>
                @foreach ($substitutions as $option)
                    <option value="{{ $option->value }}" wire:key="sub-{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="panel flex items-center justify-between gap-4 p-5">
            <div>
                <p id="press-label" class="font-semibold">Press high</p>
                <p class="mt-1 text-sm text-muted">Win the ball back faster. Tires your side quicker.</p>
            </div>
            <button type="button" role="switch" aria-labelledby="press-label"
                x-on:click="$wire.pressHigh = ! $wire.pressHigh"
                x-bind:aria-checked="$wire.pressHigh ? 'true' : 'false'"
                aria-checked="{{ $pressHigh ? 'true' : 'false' }}"
                x-bind:class="$wire.pressHigh ? 'bg-you' : 'bg-line-strong'"
                class="relative h-8 w-14 shrink-0 rounded-full transition"
            >
                <span x-bind:class="$wire.pressHigh ? 'translate-x-6' : 'translate-x-0'" class="absolute top-1 left-1 size-6 rounded-full bg-knob transition"></span>
            </button>
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">Lock in decision</button>
    </form>
</section>
