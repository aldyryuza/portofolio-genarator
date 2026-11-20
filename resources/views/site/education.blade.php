<x-site.layout :title="__('Education & certifications')" :canonical="route('education')">
    <div class="container-site space-y-20 py-16 sm:py-24">
        <h1 class="sr-only">{{ __('Education & certifications') }}</h1>

        <section aria-labelledby="education-heading">
            <x-site.section-heading :eyebrow="__('Learning')" id="education-heading">{{ __('Education') }}</x-site.section-heading>

            @if ($educations->isEmpty())
                <p class="mt-8 text-ink-muted">{{ __('Education will appear here soon.') }}</p>
            @else
                <ol class="mt-10 divide-y divide-line border-y border-line">
                    @foreach ($educations as $education)
                        <li class="grid gap-1 py-6 sm:grid-cols-[10rem_1fr] sm:gap-8">
                            <p class="text-sm text-ink-muted tabular-nums">
                                {{ $education->start_date?->format('Y') }}@if ($education->start_date) – @endif{{ $education->end_date?->format('Y') ?? ($education->isOngoing() ? __('Present') : '') }}
                            </p>
                            <div>
                                <h3 class="text-lg font-medium">{{ $education->institution }}</h3>
                                @if ($education->degree || $education->field_of_study)
                                    <p class="text-sm text-ink-muted">{{ collect([$education->degree, $education->field_of_study])->filter()->implode(' · ') }}</p>
                                @endif
                                <x-site.prose :text="$education->description" class="mt-3 max-w-2xl text-[0.95rem]" />
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section aria-labelledby="certifications-heading">
            <x-site.section-heading :eyebrow="__('Credentials')" id="certifications-heading">{{ __('Certifications') }}</x-site.section-heading>

            @if ($certifications->isEmpty())
                <p class="mt-8 text-ink-muted">{{ __('Certifications will appear here soon.') }}</p>
            @else
                <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($certifications as $certification)
                        {{-- Only public image files are shown; PDFs and private files stay with the owner. --}}
                        @php($image = $certification->file?->isPublic() && $certification->file->isImage() ? $certification->file : null)
                        <li class="flex flex-col overflow-hidden rounded-xl border border-line bg-surface">
                            @if ($image)
                                <x-site.image :media="$image" variant="thumb" sizes="(min-width: 1024px) 33vw, 100vw" class="aspect-4/3 w-full border-b border-line object-cover" />
                            @endif
                            <div class="flex flex-1 flex-col gap-1 p-5">
                                <h3 class="font-medium">{{ $certification->name }}</h3>
                                <p class="text-sm text-ink-muted">{{ $certification->issuer }}</p>
                                <p class="text-sm text-ink-muted">
                                    {{ __('Issued :date', ['date' => $certification->issue_date->translatedFormat('M Y')]) }}
                                    @if ($certification->expiry_date)
                                        · {{ $certification->isExpired() ? __('Expired :date', ['date' => $certification->expiry_date->translatedFormat('M Y')]) : __('Valid until :date', ['date' => $certification->expiry_date->translatedFormat('M Y')]) }}
                                    @endif
                                </p>
                                @if ($certification->credential_id)
                                    <p class="font-mono text-xs text-ink-muted">{{ __('ID: :id', ['id' => $certification->credential_id]) }}</p>
                                @endif
                                @if ($certification->credential_url)
                                    <a href="{{ $certification->credential_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-auto pt-3 text-sm font-medium text-accent hover:underline">
                                        {{ __('Verify credential') }} ↗<span class="sr-only"> {{ __('for :name', ['name' => $certification->name]) }}</span>
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-site.layout>
