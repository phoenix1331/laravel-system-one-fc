<span wire:poll.10s class="inline-flex" role="status" title="{{ $status->label() }}">
    <span class="size-2 rounded-full {{ $colours[$status->value] }}" aria-hidden="true"></span>
    <span class="sr-only">{{ $status->label() }}</span>
</span>
