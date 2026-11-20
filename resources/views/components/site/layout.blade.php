@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'type' => 'website',
    'noindex' => false,
])

@php
    $owner = app(\App\Support\SiteOwner::class);
    $siteName = $owner->name();
    $pageTitle = filled($title) ? "{$title} — {$siteName}" : $siteName.(filled($owner->profile()?->headline) ? ' — '.$owner->profile()->headline : '');
    $metaDescription = \Illuminate\Support\Str::limit((string) ($description ?? $owner->profile()?->summary ?? ''), 160);
    $canonicalUrl = $canonical ?? url()->current();

    $navigation = [
        ['route' => 'projects.index', 'active' => 'projects.*', 'label' => __('Work')],
        ['route' => 'about', 'active' => 'about', 'label' => __('About')],
        ['route' => 'experience', 'active' => 'experience', 'label' => __('Experience')],
        ['route' => 'education', 'active' => 'education', 'label' => __('Education')],
        ['route' => 'cv', 'active' => 'cv', 'label' => __('CV')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }}</title>
        @if ($metaDescription !== '')
            <meta name="description" content="{{ $metaDescription }}">
        @endif
        @if ($noindex)
            <meta name="robots" content="noindex, follow">
        @endif
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?? $siteName }}">
        @if ($metaDescription !== '')
            <meta property="og:description" content="{{ $metaDescription }}">
        @endif
        <meta property="og:url" content="{{ $canonicalUrl }}">
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
        @endif
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <x-theme-script />
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{ $head ?? '' }}
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">
            {{ __('Skip to content') }}
        </a>

        <header class="sticky top-0 z-40 border-b border-line/80 bg-paper/90 backdrop-blur">
            <div class="container-site flex items-center justify-between gap-6 py-4">
                <a href="{{ route('home') }}" class="font-display text-xl leading-none tracking-tight" @if (request()->routeIs('home')) aria-current="page" @endif>
                    {{ $siteName }}
                </a>

                {{-- Desktop navigation --}}
                <nav aria-label="{{ __('Main') }}" class="hidden md:block">
                    <ul class="flex items-center gap-1 text-sm">
                        @foreach ($navigation as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <li>
                                <a href="{{ route($item['route']) }}" @class(['rounded-md px-3 py-2', 'text-ink font-medium' => $isActive, 'text-ink-muted hover:text-ink' => ! $isActive]) @if ($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                        <li class="ms-1"><x-theme-toggle /></li>
                        <li class="ms-1"><a href="{{ route('contact') }}" class="btn-primary py-1.5" @if (request()->routeIs('contact')) aria-current="page" @endif>{{ __('Get in touch') }}</a></li>
                    </ul>
                </nav>

                {{-- Mobile navigation: native disclosure, works without JavaScript --}}
                <div class="flex items-center gap-2 md:hidden">
                <x-theme-toggle />
                <details class="group relative">
                    <summary class="btn-secondary cursor-pointer list-none px-3 [&::-webkit-details-marker]:hidden">
                        <span class="sr-only">{{ __('Menu') }}</span>
                        <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </summary>
                    <nav aria-label="{{ __('Main') }}" class="absolute end-0 top-full mt-2 w-56 rounded-lg border border-line bg-surface p-2 shadow-lg">
                        <ul class="space-y-0.5 text-sm">
                            @foreach ($navigation as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <li>
                                    <a href="{{ route($item['route']) }}" @class(['block rounded-md px-3 py-2', 'bg-paper font-medium' => $isActive, 'text-ink-muted' => ! $isActive]) @if ($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                            <li><a href="{{ route('contact') }}" class="block rounded-md px-3 py-2 font-medium text-accent">{{ __('Get in touch') }}</a></li>
                        </ul>
                    </nav>
                </details>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line">
            <div class="container-site flex flex-col gap-4 py-8 text-sm text-ink-muted sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ now()->year }} {{ $siteName }}</p>
                @php($links = collect($owner->socialLinks()))
                @if ($links->isNotEmpty())
                    <ul class="flex flex-wrap gap-x-5 gap-y-2" aria-label="{{ __('Elsewhere') }}">
                        @foreach ($links as $link)
                            <li><a href="{{ $link->url }}" rel="me noopener noreferrer" target="_blank" class="hover:text-ink">{{ $link->displayLabel() }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </footer>
    </body>
</html>
