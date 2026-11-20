@php
    $owner = app(\App\Support\SiteOwner::class);
    $profile = $owner->profile();
    $person = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        'name' => $owner->name(),
        'jobTitle' => $profile?->headline,
        'url' => route('home'),
        'image' => $owner->avatarUrl('large'),
        'sameAs' => collect($owner->socialLinks())->pluck('url')->values()->all() ?: null,
    ]);
@endphp

<x-site.layout :canonical="route('home')" :image="$owner->avatarUrl('large')">
    <x-slot:head>
        {{-- JSON_HEX_* keeps user content from closing the script tag. --}}
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($person, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="grid-motif border-b border-line" aria-labelledby="hero-heading">
        <div class="container-site grid gap-10 py-20 sm:py-28 lg:grid-cols-[1fr_auto] lg:items-end">
            <div class="max-w-3xl">
                @if ($profile && $profile->availability_status !== \App\Enums\AvailabilityStatus::NotAvailable)
                    <p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium">
                        <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                        {{ $profile->availability_status->label() }}
                    </p>
                @endif

                <h1 id="hero-heading" class="mt-6 font-display text-5xl leading-[1.05] tracking-tight text-balance sm:text-6xl lg:text-7xl">
                    {{ $profile?->headline ?? $owner->name() }}
                </h1>

                @if ($profile?->headline)
                    <p class="mt-4 text-lg text-ink-muted">{{ $owner->name() }}@if ($profile->location_public) · {{ $profile->location_public }}@endif</p>
                @endif

                @if ($profile?->summary)
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-ink/85 text-pretty">{{ $profile->summary }}</p>
                @elseif (! $profile)
                    <p class="mt-6 max-w-2xl text-lg text-ink-muted">{{ __('This portfolio is being prepared. Please check back soon.') }}</p>
                @endif

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('projects.index') }}" class="btn-primary px-5 py-2.5">{{ __('View work') }}</a>
                    <a href="{{ route('contact') }}" class="btn-secondary px-5 py-2.5">{{ __('Get in touch') }}</a>
                </div>
            </div>

            @if ($avatar = $owner->avatarUrl('thumb'))
                <img src="{{ $avatar }}" alt="{{ $profile->avatar->alt_text ?? $owner->name() }}" width="224" height="224" class="size-40 rounded-2xl border border-line object-cover sm:size-56">
            @endif
        </div>
    </section>

    {{-- Featured work --}}
    @if ($featured->isNotEmpty())
        <section class="container-site py-20" aria-labelledby="work-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-site.section-heading :eyebrow="__('Selected work')" id="work-heading">{{ __('Projects') }}</x-site.section-heading>
                <a href="{{ route('projects.index') }}" class="text-sm font-medium text-accent hover:underline">{{ __('All projects') }} →</a>
            </div>
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($featured as $project)
                    <x-site.project-card :project="$project" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Expertise --}}
    @if ($skillGroups->isNotEmpty())
        <section class="border-y border-line bg-surface" aria-labelledby="skills-heading">
            <div class="container-site py-20">
                <x-site.section-heading :eyebrow="__('Expertise')" id="skills-heading">{{ __('What I work with') }}</x-site.section-heading>
                <dl class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($skillGroups as $group)
                        <div>
                            <dt class="text-sm font-medium">{{ $group->name }}</dt>
                            <dd class="mt-3">
                                <ul class="flex flex-wrap gap-1.5">
                                    @foreach ($group->skills as $skill)
                                        <li><a href="{{ route('projects.index', ['skill' => $skill->slug]) }}" class="chip hover:border-accent hover:text-accent">{{ $skill->name }}</a></li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    {{-- Recent experience --}}
    @if ($experiences->isNotEmpty())
        <section class="container-site py-20" aria-labelledby="experience-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-site.section-heading :eyebrow="__('Career')" id="experience-heading">{{ __('Recent experience') }}</x-site.section-heading>
                <a href="{{ route('experience') }}" class="text-sm font-medium text-accent hover:underline">{{ __('Full history') }} →</a>
            </div>
            <ol class="mt-10">
                @foreach ($experiences as $experience)
                    <x-site.experience-item :experience="$experience" compact />
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Call to action --}}
    @if ($profile)
        <section class="border-t border-line" aria-labelledby="cta-heading">
            <div class="container-site flex flex-col items-start gap-6 py-20 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="cta-heading" class="max-w-xl font-display text-3xl leading-tight sm:text-4xl">{{ __('Have a project or role in mind?') }}</h2>
                <a href="{{ route('contact') }}" class="btn-primary px-5 py-2.5">{{ __('Get in touch') }}</a>
            </div>
        </section>
    @endif
</x-site.layout>
