<x-site.layout :title="__('CV')" :canonical="route('cv')">
    <div class="container-site py-16 sm:py-24">
        <x-site.section-heading :eyebrow="__('Résumé')" level="h1">{{ __('Curriculum vitae') }}</x-site.section-heading>

        @if ($default === null)
            <p class="mt-10 text-ink-muted">
                {{ __('A downloadable CV is not available yet.') }}
                <a href="{{ route('contact') }}" class="text-accent hover:underline">{{ __('Get in touch to request one.') }}</a>
            </p>
        @else
            <div class="mt-10 flex flex-col gap-6 rounded-xl border border-line bg-surface p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <p class="text-lg font-medium">{{ $default->title }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ __('Version :version', ['version' => $default->version]) }} · {{ strtoupper($default->language) }} · PDF
                        @if ($default->published_at) · {{ __('Updated :date', ['date' => $default->published_at->translatedFormat('j M Y')]) }} @endif
                    </p>
                </div>
                <a href="{{ route('cv.download', $default) }}" class="btn-primary px-5 py-2.5" download>{{ __('Download PDF') }}</a>
            </div>

            @if ($resumes->count() > 1)
                <h2 class="mt-14 text-lg font-medium">{{ __('Other versions') }}</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    @foreach ($resumes->reject(fn ($resume) => $resume->is($default)) as $resume)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-4 text-sm">
                            <span>{{ $resume->title }} <span class="text-ink-muted">· {{ strtoupper($resume->language) }} · {{ $resume->version }}</span></span>
                            <a href="{{ route('cv.download', $resume) }}" class="font-medium text-accent hover:underline" download>
                                {{ __('Download') }}<span class="sr-only"> {{ $resume->title }} {{ $resume->version }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        <p class="mt-14 text-sm text-ink-muted">
            {{ __('Prefer the web version?') }}
            <a href="{{ route('experience') }}" class="text-accent hover:underline">{{ __('See experience') }}</a> ·
            <a href="{{ route('education') }}" class="text-accent hover:underline">{{ __('Education & certifications') }}</a>
        </p>
    </div>
</x-site.layout>
