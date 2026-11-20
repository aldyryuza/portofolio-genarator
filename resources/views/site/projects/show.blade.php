@php
    $sections = array_filter([
        __('Overview') => $project->summary,
        __('The challenge') => $project->challenge,
        __('Approach & responsibilities') => $project->solution,
        __('Outcome') => $project->outcome,
    ], 'filled');

    $period = collect([$project->start_date?->translatedFormat('M Y'), $project->start_date ? ($project->end_date?->translatedFormat('M Y') ?? __('Ongoing')) : null])
        ->filter()->implode(' – ');
@endphp

<x-site.layout
    :title="$project->meta_title ?: $project->title"
    :description="$project->meta_description ?: $project->excerpt"
    :canonical="route('projects.show', $project->slug)"
    :image="$cover?->url('large')"
    type="article"
    :noindex="$isPreview">

    @if ($isPreview)
        <div class="border-b border-accent/30 bg-accent/10" role="status">
            <p class="container-site py-3 text-sm text-accent">
                <strong>{{ __('Preview') }}</strong> — {{ __('This project is not published. Only you and people with the preview link can see it. Private images are hidden.') }}
            </p>
        </div>
    @endif

    <article>
        <header class="border-b border-line">
            <div class="container-site py-14 sm:py-20">
                <a href="{{ route('projects.index') }}" class="text-sm text-ink-muted hover:text-ink">← {{ __('All projects') }}</a>

                @if ($project->category)
                    <p class="eyebrow mt-8">
                        <a href="{{ route('projects.index', ['category' => $project->category->slug]) }}" class="hover:text-ink">{{ $project->category->name }}</a>
                    </p>
                @endif

                <h1 class="mt-3 max-w-4xl font-display text-5xl leading-[1.05] tracking-tight text-balance sm:text-6xl">{{ $project->title }}</h1>

                @if ($project->excerpt)
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-ink/85">{{ $project->excerpt }}</p>
                @endif

                <dl class="mt-10 grid gap-6 border-t border-line pt-6 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    @if ($project->role)
                        <div><dt class="eyebrow">{{ __('Role') }}</dt><dd class="mt-1.5">{{ $project->role }}</dd></div>
                    @endif
                    @if ($client = $project->displayClient())
                        <div><dt class="eyebrow">{{ __('Client') }}</dt><dd class="mt-1.5">{{ $client }}</dd></div>
                    @endif
                    @if ($period !== '')
                        <div><dt class="eyebrow">{{ __('Timeline') }}</dt><dd class="mt-1.5">{{ $period }}</dd></div>
                    @endif
                    @if ($project->skills->isNotEmpty())
                        <div>
                            <dt class="eyebrow">{{ __('Technologies') }}</dt>
                            <dd class="mt-1.5">
                                <ul class="flex flex-wrap gap-1.5">
                                    @foreach ($project->skills as $skill)
                                        <li><a href="{{ route('projects.index', ['skill' => $skill->slug]) }}" class="chip hover:border-accent hover:text-accent">{{ $skill->name }}</a></li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </header>

        @if ($cover)
            <div class="container-site pt-10">
                <x-site.image :media="$cover" sizes="(min-width: 1152px) 1152px, 100vw" eager class="w-full rounded-xl border border-line" />
            </div>
        @endif

        @if ($sections !== [])
            <div class="container-site space-y-14 py-16">
                @foreach ($sections as $heading => $text)
                    <section class="grid gap-4 lg:grid-cols-[14rem_1fr] lg:gap-12">
                        <h2 class="font-display text-2xl leading-tight">{{ $heading }}</h2>
                        <x-site.prose :text="$text" class="max-w-2xl text-lg" />
                    </section>
                @endforeach
            </div>
        @endif

        @if ($gallery->isNotEmpty())
            <section class="border-t border-line bg-surface" aria-labelledby="gallery-heading">
                <div class="container-site py-16">
                    <h2 id="gallery-heading" class="font-display text-3xl">{{ __('Gallery') }}</h2>
                    <div class="mt-8 grid gap-6 md:grid-cols-2">
                        @foreach ($gallery as $item)
                            <figure>
                                <x-site.image :media="$item->media" sizes="(min-width: 768px) 50vw, 100vw" class="w-full rounded-lg border border-line" />
                                @if ($item->caption || $item->media->caption || $item->media->credit)
                                    <figcaption class="mt-2 text-sm text-ink-muted">
                                        {{ $item->caption ?? $item->media->caption }}
                                        @if ($item->media->credit) <span class="block text-xs">{{ $item->media->credit }}</span> @endif
                                    </figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($project->videos->isNotEmpty())
            <section class="border-t border-line" aria-labelledby="videos-heading">
                <div class="container-site py-16">
                    <h2 id="videos-heading" class="font-display text-3xl">{{ __('Video') }}</h2>
                    <div class="mt-8 grid gap-8 md:grid-cols-2">
                        @foreach ($project->videos as $video)
                            {{-- src is built from a validated provider + id, never from stored HTML. --}}
                            <figure>
                                <div class="aspect-video overflow-hidden rounded-lg border border-line bg-surface">
                                    <iframe src="{{ $video->embedUrl() }}"
                                        title="{{ $video->title ?? __('Video for :project', ['project' => $project->title]) }}"
                                        class="size-full" loading="lazy"
                                        allow="encrypted-media; picture-in-picture; fullscreen"
                                        referrerpolicy="strict-origin-when-cross-origin"
                                        allowfullscreen></iframe>
                                </div>
                                @if ($video->title)
                                    <figcaption class="mt-2 text-sm text-ink-muted">{{ $video->title }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($project->tags->isNotEmpty())
            <footer class="container-site border-t border-line py-8">
                <ul class="flex flex-wrap gap-1.5" aria-label="{{ __('Tags') }}">
                    @foreach ($project->tags as $tag)
                        <li class="chip">#{{ $tag->name }}</li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </article>
</x-site.layout>
