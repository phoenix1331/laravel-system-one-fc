@props(['title' => null, 'context' => null])

<!DOCTYPE html>
<html lang="en-GB" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? "{$title} · System One FC" : 'System One FC' }}</title>

    {{-- Pick the theme before first paint: saved choice, then the OS setting, then dark. --}}
    <script>
        (() => {
            let theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            theme ??= window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            document.documentElement.classList.toggle('dark', theme === 'dark');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen">
    <div class="mx-auto flex min-h-screen max-w-[1160px] flex-col px-4 sm:px-6">
        <header class="flex items-center justify-between gap-4 py-6">
            <a href="{{ route('home') }}" class="rounded-xl" aria-label="System One FC home">
                <x-logo />
            </a>

            <div class="flex items-center gap-4 sm:gap-6">
                @if ($context)
                    <span class="hidden text-sm text-muted sm:inline">{{ $context }}</span>
                @else
                    <nav class="hidden items-center gap-6 text-sm text-muted sm:flex" aria-label="Main">
                        <a href="{{ route('home') }}#how-it-works" class="hover:text-text">How it works</a>
                        @if (config('app.repository_url'))
                            <a href="{{ config('app.repository_url') }}" class="hover:text-text">GitHub</a>
                        @endif
                    </nav>
                @endif
                <x-theme-toggle />
            </div>
        </header>

        <main class="flex-1 pb-12">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
