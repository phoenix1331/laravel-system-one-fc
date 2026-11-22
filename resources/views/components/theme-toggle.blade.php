{{-- Switches the .dark class on <html> and remembers the choice in localStorage. --}}
<button
    type="button"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    x-on:click="dark = ! dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light')"
    x-bind:aria-label="dark ? 'Switch to light theme' : 'Switch to dark theme'"
    aria-label="Switch theme"
    class="grid size-11 place-items-center rounded-xl border border-line text-text transition hover:bg-surface2"
>
    {{-- Sun, shown in dark mode --}}
    <svg x-show="dark" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </svg>
    {{-- Moon, shown in light mode --}}
    <svg x-show="! dark" x-cloak class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
    </svg>
</button>
