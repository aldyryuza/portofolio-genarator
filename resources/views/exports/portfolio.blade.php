{{-- Rendered by dompdf: CSS 2.1 only (tables/floats, no flex/grid). All text is escaped by Blade. --}}
@php
    $profile = $doc->profile;
    $paragraphs = fn (?string $text) => filled($text) ? preg_split('/\R{2,}/', trim($text)) : [];
@endphp
<!DOCTYPE html>
<html lang="{{ $doc->options->locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->title() }}</title>
    @include('exports.partials.styles')
</head>
<body>
    <div class="footer">
        <span>{{ $doc->ownerName }}</span>
        <span style="float: right;">{{ __('Page') }} <span class="page-number"></span></span>
    </div>

    {{-- Title page --}}
    <div class="page">
        <table style="width: 100%; margin-top: 40mm;">
            <tr>
                <td style="vertical-align: bottom;">
                    <p class="eyebrow">{{ __('Portfolio') }}</p>
                    <h1>{{ $doc->ownerName }}</h1>
                    @if ($profile?->headline)
                        <p class="muted" style="font-size: 13pt; margin-top: 3mm;">{{ $profile->headline }}</p>
                    @endif
                </td>
                @if ($doc->avatar)
                    <td style="width: 36mm; text-align: right; vertical-align: bottom;">
                        <img class="avatar" src="{{ $doc->avatar->dataUri() }}" alt="{{ $doc->avatar->alt ?: $doc->ownerName }}">
                    </td>
                @endif
            </tr>
        </table>

        @if ($profile?->summary)
            <div class="rule"></div>
            <p style="font-size: 11.5pt; max-width: 150mm;">{{ $profile->summary }}</p>
        @endif

        @if ($profile)
            <table class="meta" style="margin-top: 10mm;">
                @if ($profile->location_public)
                    <tr><td class="label">{{ __('Based in') }}</td><td>{{ $profile->location_public }}</td></tr>
                @endif
                @if ($profile->public_email)
                    <tr><td class="label">{{ __('Email') }}</td><td>{{ $profile->public_email }}</td></tr>
                @endif
                <tr><td class="label">{{ __('Website') }}</td><td>{{ route('home') }}</td></tr>
            </table>
        @endif

        @if ($doc->projects->isNotEmpty())
            <p class="eyebrow" style="margin-top: 12mm;">{{ __('Projects in this document') }}</p>
            <ol style="padding-left: 5mm;">
                @foreach ($doc->projects as $project)
                    <li>{{ $project->title }}@if ($project->category) <span class="muted">— {{ $project->category->name }}</span>@endif</li>
                @endforeach
            </ol>
        @endif
    </div>

    @if ($profile?->biography)
        <div class="page">
            <p class="eyebrow">{{ __('About') }}</p>
            <h2>{{ $doc->ownerName }}</h2>
            @foreach ($paragraphs($profile->biography) as $paragraph)
                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
            @endforeach
        </div>
    @endif

    {{-- One project per page; long case studies flow onto the next page. --}}
    @forelse ($doc->projects as $project)
        <div class="page">
            @if ($project->category)
                <p class="eyebrow">{{ $project->category->name }}</p>
            @endif
            <h2>{{ $project->title }}</h2>
            @if ($project->excerpt)
                <p class="muted" style="font-size: 11pt;">{{ $project->excerpt }}</p>
            @endif

            @php
                $period = collect([
                    $project->start_date?->translatedFormat('M Y'),
                    $project->start_date ? ($project->end_date?->translatedFormat('M Y') ?? __('Ongoing')) : null,
                ])->filter()->implode(' – ');
            @endphp
            <table class="meta">
                @if ($project->role)
                    <tr><td class="label">{{ __('Role') }}</td><td>{{ $project->role }}</td></tr>
                @endif
                @if ($client = $doc->client($project))
                    <tr><td class="label">{{ __('Client') }}</td><td>{{ $client }}</td></tr>
                @endif
                @if ($period !== '')
                    <tr><td class="label">{{ __('Timeline') }}</td><td>{{ $period }}</td></tr>
                @endif
                @if ($project->skills->isNotEmpty())
                    <tr><td class="label">{{ __('Technologies') }}</td><td>@foreach ($project->skills as $skill)<span class="chip">{{ $skill->name }}</span>@endforeach</td></tr>
                @endif
            </table>

            @if ($cover = $doc->cover($project))
                <img class="cover-image" src="{{ $cover->dataUri() }}" alt="{{ $cover->alt }}">
            @endif

            @foreach ([__('Overview') => $project->summary, __('The challenge') => $project->challenge, __('Approach & responsibilities') => $project->solution, __('Outcome') => $project->outcome] as $heading => $text)
                @if (filled($text))
                    <div @class(['highlight' => $heading === __('Outcome')])>
                        <h3>{{ $heading }}</h3>
                        @foreach ($paragraphs($text) as $paragraph)
                            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($doc->options->includeConfidential && filled($project->confidential_notes))
                <div class="highlight">
                    <h3>{{ __('Confidential notes') }}</h3>
                    @foreach ($paragraphs($project->confidential_notes) as $paragraph)
                        <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                    @endforeach
                </div>
            @endif

            @if ($gallery = $doc->gallery($project))
                <h3>{{ __('Gallery') }}</h3>
                <table class="gallery">
                    @foreach (array_chunk($gallery, 2) as $row)
                        <tr class="no-break">
                            @foreach ($row as $image)
                                <td><img src="{{ $image->dataUri() }}" alt="{{ $image->alt }}"></td>
                            @endforeach
                            @if (count($row) === 1) <td></td> @endif
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @empty
        <div class="page">
            <h2>{{ __('Projects') }}</h2>
            <p class="muted">{{ __('No projects match the selected export options.') }}</p>
        </div>
    @endforelse

    @if ($doc->experiences->isNotEmpty())
        <div class="page">
            <p class="eyebrow">{{ __('Career') }}</p>
            <h2>{{ __('Experience') }}</h2>
            <table class="timeline" style="width: 100%;">
                @foreach ($doc->experiences as $experience)
                    <tr class="no-break">
                        <td class="when">
                            {{ $experience->start_date->translatedFormat('M Y') }} –
                            {{ $experience->is_current ? __('Present') : $experience->end_date?->translatedFormat('M Y') }}
                        </td>
                        <td>
                            <strong>{{ $experience->title }}</strong><br>
                            <span class="muted">{{ $experience->company }} · {{ $experience->employment_type->label() }}@if ($experience->location) · {{ $experience->location }}@endif</span>
                            @foreach ($paragraphs($experience->description) as $paragraph)
                                <p style="margin-top: 1.5mm;">{!! nl2br(e(trim($paragraph))) !!}</p>
                            @endforeach
                            @if (! empty($experience->achievements_json))
                                <ul>
                                    @foreach ($experience->achievements_json as $achievement)
                                        <li>{{ $achievement }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($doc->educations->isNotEmpty() || $doc->certifications->isNotEmpty())
        <div class="page">
            @if ($doc->educations->isNotEmpty())
                <p class="eyebrow">{{ __('Learning') }}</p>
                <h2>{{ __('Education') }}</h2>
                <table class="timeline" style="width: 100%;">
                    @foreach ($doc->educations as $education)
                        <tr class="no-break">
                            <td class="when">{{ $education->start_date?->format('Y') }}@if ($education->start_date) – @endif{{ $education->end_date?->format('Y') ?? ($education->isOngoing() ? __('Present') : '') }}</td>
                            <td>
                                <strong>{{ $education->institution }}</strong><br>
                                <span class="muted">{{ collect([$education->degree, $education->field_of_study])->filter()->implode(' · ') }}</span>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($doc->certifications->isNotEmpty())
                <h2 style="margin-top: 6mm;">{{ __('Certifications') }}</h2>
                <table class="timeline" style="width: 100%;">
                    @foreach ($doc->certifications as $certification)
                        <tr class="no-break">
                            <td class="when">{{ $certification->issue_date->translatedFormat('M Y') }}</td>
                            <td>
                                <strong>{{ $certification->name }}</strong><br>
                                <span class="muted">{{ $certification->issuer }}@if ($certification->credential_id) · {{ __('ID: :id', ['id' => $certification->credential_id]) }}@endif</span>
                                @if ($certification->credential_url)
                                    <br><span class="accent">{{ $certification->credential_url }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endif
</body>
</html>
