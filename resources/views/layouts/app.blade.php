@php
    $navigation = [
        '' => [
            ['route' => 'admin.dashboard', 'label' => __('Dashboard'), 'active' => 'admin.dashboard'],
            ['route' => 'admin.inbox', 'label' => __('Inbox'), 'active' => 'admin.inbox', 'count' => \App\Models\ContactMessage::where('status', 'new')->count()],
        ],
        __('Content') => [
            ['route' => 'admin.projects.index', 'label' => __('Projects'), 'active' => 'admin.projects.*'],
            ['route' => 'admin.media', 'label' => __('Media'), 'active' => 'admin.media'],
            ['route' => 'admin.categories', 'label' => __('Categories'), 'active' => 'admin.categories'],
            ['route' => 'admin.tags', 'label' => __('Tags'), 'active' => 'admin.tags'],
            ['route' => 'admin.skills', 'label' => __('Skills'), 'active' => 'admin.skill*'],
            ['route' => 'admin.exports', 'label' => __('Exports'), 'active' => 'admin.exports*'],
        ],
        __('Career') => [
            ['route' => 'admin.experiences', 'label' => __('Experience'), 'active' => 'admin.experiences'],
            ['route' => 'admin.educations', 'label' => __('Education'), 'active' => 'admin.educations'],
            ['route' => 'admin.certifications', 'label' => __('Certifications'), 'active' => 'admin.certifications'],
            ['route' => 'admin.resumes', 'label' => __('CV versions'), 'active' => 'admin.resumes'],
        ],
        __('Profile') => [
            ['route' => 'admin.profile', 'label' => __('Public profile'), 'active' => 'admin.profile'],
            ['route' => 'admin.social-links', 'label' => __('Social links'), 'active' => 'admin.social-links'],
            ['route' => 'admin.account', 'label' => __('Account'), 'active' => 'admin.account'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper font-sans text-ink antialiased" x-data="{ open: false }">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">
            {{ __('Skip to content') }}
        </a>

        <header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur lg:hidden">
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold tracking-wide">
                    {{ config('app.name') }} <span class="font-normal text-ink-muted">/ {{ __('Admin') }}</span>
                </a>
                <div class="flex items-center gap-2">
                <x-theme-toggle />
                <button type="button" class="btn-secondary px-3" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="admin-nav">
                    <span class="sr-only">{{ __('Toggle navigation') }}</span>
                    <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                </button>
                </div>
            </div>
        </header>

        <div class="lg:flex">
            <nav id="admin-nav" aria-label="{{ __('Admin') }}"
                class="hidden border-b border-line bg-surface px-3 py-4 lg:sticky lg:top-0 lg:block lg:h-screen lg:w-60 lg:shrink-0 lg:overflow-y-auto lg:border-e lg:border-b-0"
                x-bind:class="{ 'hidden': ! open }">
                <div class="mb-4 hidden items-center justify-between gap-2 px-3 lg:flex">
                    <a href="{{ route('admin.dashboard') }}" class="text-sm font-semibold tracking-wide">
                        {{ config('app.name') }} <span class="font-normal text-ink-muted">/ {{ __('Admin') }}</span>
                    </a>
                    <x-theme-toggle class="size-8" />
                </div>

                @foreach ($navigation as $group => $items)
                    <div @class(['mt-4' => $group !== ''])>
                        @if ($group !== '')
                            <p class="px-3 pb-1 text-xs font-medium tracking-wide text-ink-muted uppercase">{{ $group }}</p>
                        @endif
                        <ul class="space-y-0.5">
                            @foreach ($items as $item)
                                @php($isActive = request()->routeIs($item['active']))
                                <li>
                                    <a href="{{ route($item['route']) }}" wire:navigate
                                        @class([
                                            'block rounded-md px-3 py-1.5 text-sm',
                                            'bg-paper font-medium text-ink' => $isActive,
                                            'text-ink-muted hover:bg-paper hover:text-ink' => ! $isActive,
                                        ])
                                        @if ($isActive) aria-current="page" @endif>
                                        <span class="flex items-center justify-between gap-2">
                                            {{ $item['label'] }}
                                            @if (! empty($item['count']))
                                                <span class="rounded-full bg-accent px-1.5 text-xs font-medium text-accent-ink">{{ $item['count'] }}<span class="sr-only"> {{ __('unread') }}</span></span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <ul class="mt-6 space-y-0.5 border-t border-line pt-4">
                    <li>
                        <a href="{{ route('home') }}" class="block rounded-md px-3 py-1.5 text-sm text-ink-muted hover:bg-paper hover:text-ink">{{ __('View site') }} ↗</a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-3 py-1.5 text-left text-sm text-ink-muted hover:bg-paper hover:text-ink">{{ __('Log out') }}</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <main id="main" class="min-w-0 flex-1 px-4 py-8 sm:px-6 sm:py-10 lg:px-10">
                <div class="mx-auto max-w-6xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
