{{-- Deliberately free of database calls so it still renders when the app is failing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('title') — {{ config('app.name') }}</title>
        <x-theme-script />
        @if (file_exists(public_path('build/manifest.json')))
            @vite(['resources/css/app.css'])
        @endif
    </head>
    <body class="grid-motif grid min-h-screen place-items-center bg-paper px-5 font-sans text-ink antialiased">
        <main class="max-w-lg py-20 text-center">
            <p class="eyebrow">{{ __('Error :code', ['code' => trim($__env->yieldContent('code'))]) }}</p>
            <h1 class="mt-3 font-display text-5xl leading-tight tracking-tight">@yield('title')</h1>
            <p class="mt-4 text-ink-muted">@yield('message')</p>
            <a href="{{ url('/') }}" class="btn-primary mt-8 px-5 py-2.5">{{ __('Back to home') }}</a>
        </main>
    </body>
</html>
