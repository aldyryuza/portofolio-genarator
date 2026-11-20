@props(['experience', 'compact' => false, 'headingLevel' => 'h3'])

<li {{ $attributes->class('relative grid gap-1 border-s border-line ps-6 pb-10 last:pb-0 sm:grid-cols-[10rem_1fr] sm:gap-8 sm:border-0 sm:ps-0') }}>
    <span class="absolute -start-[5px] top-1.5 size-2.5 rounded-full border-2 border-paper {{ $experience->is_current ? 'bg-accent' : 'bg-ink-muted/40' }} sm:hidden" aria-hidden="true"></span>

    <p class="text-sm text-ink-muted tabular-nums">
        <time datetime="{{ $experience->start_date->format('Y-m') }}">{{ $experience->start_date->translatedFormat('M Y') }}</time>
        –
        @if ($experience->is_current)
            <span class="font-medium text-accent">{{ __('Present') }}</span>
        @else
            <time datetime="{{ $experience->end_date?->format('Y-m') }}">{{ $experience->end_date?->translatedFormat('M Y') }}</time>
        @endif
    </p>

    <div>
        <{{ $headingLevel }} class="text-lg font-medium">{{ $experience->title }}</{{ $headingLevel }}>
        <p class="text-sm text-ink-muted">
            {{ $experience->company }}
            · {{ $experience->employment_type->label() }}
            @if ($experience->location) · {{ $experience->location }} @endif
        </p>

        @unless ($compact)
            <x-site.prose :text="$experience->description" class="mt-3 max-w-2xl text-[0.95rem]" />

            @if (! empty($experience->achievements_json))
                <ul class="mt-3 max-w-2xl list-disc space-y-1.5 ps-5 text-[0.95rem] text-ink/90 marker:text-accent">
                    @foreach ($experience->achievements_json as $achievement)
                        <li>{{ $achievement }}</li>
                    @endforeach
                </ul>
            @endif
        @endunless
    </div>
</li>
