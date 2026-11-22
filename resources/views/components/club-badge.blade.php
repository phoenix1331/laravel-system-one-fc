{{--
    A club badge, or a coloured initial when there is no badge file for the club.
    Badges are decorative next to the club name, so they carry no alt text.
--}}
@props(['name', 'size' => 'size-10'])

@php $url = App\Models\Team::badgeUrl($name); @endphp

@if ($url)
    <img src="{{ $url }}" alt="" {{ $attributes->merge(['class' => "{$size} shrink-0 object-contain"]) }} loading="lazy" decoding="async">
@else
    <span {{ $attributes->merge(['class' => "{$size} grid shrink-0 place-items-center rounded-full bg-surface2 font-display font-bold text-muted"]) }} aria-hidden="true">{{ Str::substr($name, 0, 1) }}</span>
@endif
