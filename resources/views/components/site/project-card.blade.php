@props(['project', 'headingLevel' => 'h3'])

@php($cover = $project->cover?->isPublic() ? $project->cover : null)

<article {{ $attributes->class('group relative flex flex-col overflow-hidden rounded-xl border border-line bg-surface transition-shadow hover:shadow-md') }}>
    <div class="aspect-16/10 overflow-hidden bg-paper">
        @if ($cover)
            <x-site.image :media="$cover" variant="thumb" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.02]" />
        @else
            <div class="grid-motif flex size-full items-end p-4" aria-hidden="true">
                <span class="font-display text-3xl text-ink-muted/60">{{ preg_match('/[\p{L}\p{N}]/u', $project->title, $initial) ? mb_strtoupper($initial[0]) : '' }}</span>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        @if ($project->category)
            <p class="eyebrow">{{ $project->category->name }}</p>
        @endif

        <{{ $headingLevel }} class="font-display text-2xl leading-tight">
            {{-- The stretched link makes the whole card clickable while keeping one accessible link. --}}
            <a href="{{ route('projects.show', $project->slug) }}" class="after:absolute after:inset-0 focus-visible:outline-none group-focus-within:underline">{{ $project->title }}</a>
        </{{ $headingLevel }}>

        @if ($project->excerpt)
            <p class="text-sm leading-relaxed text-ink-muted">{{ $project->excerpt }}</p>
        @endif

        @if ($project->outcome)
            <p class="border-s-2 border-accent ps-3 text-sm text-ink">
                <span class="sr-only">{{ __('Outcome:') }}</span>{{ \Illuminate\Support\Str::limit($project->outcome, 120) }}
            </p>
        @endif

        @if ($project->skills->isNotEmpty())
            <ul class="mt-auto flex flex-wrap gap-1.5 pt-2" aria-label="{{ __('Technologies') }}">
                @foreach ($project->skills->take(4) as $skill)
                    <li class="chip">{{ $skill->name }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</article>
