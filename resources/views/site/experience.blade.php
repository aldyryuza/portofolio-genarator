<x-site.layout :title="__('Experience')" :canonical="route('experience')">
    <div class="container-site py-16 sm:py-24">
        <x-site.section-heading :eyebrow="__('Career')" level="h1">{{ __('Experience') }}</x-site.section-heading>

        @if ($experiences->isEmpty())
            <p class="mt-10 text-ink-muted">{{ __('Work history will appear here soon.') }}</p>
        @else
            <ol class="mt-12">
                @foreach ($experiences as $experience)
                    <x-site.experience-item :experience="$experience" heading-level="h2" />
                @endforeach
            </ol>
        @endif
    </div>
</x-site.layout>
