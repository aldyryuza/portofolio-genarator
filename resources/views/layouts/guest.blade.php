<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased">
        <x-theme-toggle class="absolute end-4 top-4" />

        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <a href="{{ route('home') }}" class="mb-8 text-sm font-semibold tracking-wide text-ink-muted hover:text-ink">
                {{ config('app.name') }}
            </a>

            <div class="card w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
