@php
    $owner = app(\App\Support\SiteOwner::class);
    $profile = $owner->profile();
@endphp

<x-site.layout :title="__('About')" :description="$profile?->summary" :canonical="route('about')" :image="$owner->avatarUrl('large')">
    <div class="container-site py-16 sm:py-24">
        <div class="grid gap-12 lg:grid-cols-[1fr_18rem]">
            <article class="max-w-2xl">
                <p class="eyebrow">{{ __('About') }}</p>
                <h1 class="mt-2 font-display text-5xl leading-tight tracking-tight">{{ $owner->name() }}</h1>
                @if ($profile?->headline)
                    <p class="mt-3 text-lg text-ink-muted">{{ $profile->headline }}</p>
                @endif

                @if ($profile?->biography || $profile?->summary)
                    <x-site.prose :text="$profile->biography ?: $profile->summary" class="mt-8 text-lg" />
                @else
                    <p class="mt-8 text-ink-muted">{{ __('A biography will appear here soon.') }}</p>
                @endif
            </article>

            <aside class="space-y-8 lg:pt-10" aria-label="{{ __('Details') }}">
                @if ($avatar = $owner->avatarUrl('large'))
                    <img src="{{ $avatar }}" alt="{{ $profile->avatar->alt_text ?? $owner->name() }}" class="aspect-square w-full max-w-72 rounded-2xl border border-line object-cover">
                @endif

                <dl class="space-y-4 text-sm">
                    @if ($profile?->location_public)
                        <div>
                            <dt class="eyebrow">{{ __('Based in') }}</dt>
                            <dd class="mt-1">{{ $profile->location_public }}</dd>
                        </div>
                    @endif
                    @if ($profile)
                        <div>
                            <dt class="eyebrow">{{ __('Availability') }}</dt>
                            <dd class="mt-1">{{ $profile->availability_status->label() }}</dd>
                        </div>
                    @endif
                    @if ($profile?->public_email)
                        <div>
                            <dt class="eyebrow">{{ __('Email') }}</dt>
                            <dd class="mt-1"><a href="mailto:{{ $profile->public_email }}" class="text-accent hover:underline">{{ $profile->public_email }}</a></dd>
                        </div>
                    @endif
                </dl>

                @php($links = collect($owner->socialLinks()))
                @if ($links->isNotEmpty())
                    <div>
                        <p class="eyebrow">{{ __('Elsewhere') }}</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($links as $link)
                                <li><a href="{{ $link->url }}" rel="me noopener noreferrer" target="_blank" class="text-accent hover:underline">{{ $link->displayLabel() }} ↗</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>

        @if ($skillGroups->isNotEmpty())
            <section class="mt-20 border-t border-line pt-12" aria-labelledby="skills-heading">
                <x-site.section-heading :eyebrow="__('Expertise')" id="skills-heading">{{ __('Skills') }}</x-site.section-heading>
                <dl class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
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
            </section>
        @endif
    </div>
</x-site.layout>
