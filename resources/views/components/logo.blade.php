{{--
    The one place the logo lives. Swap the mark here and it changes everywhere.
    Inverted colours mean it reads correctly in both themes without separate files.
--}}
@props(['wordmark' => true])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <span class="grid size-10 place-items-center rounded-xl bg-text font-display text-sm font-bold tracking-tight text-bg" aria-hidden="true">S1</span>
    @if ($wordmark)
        <span class="font-display text-lg font-bold tracking-tight">System One FC</span>
    @endif
</span>
