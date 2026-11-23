{{-- Professional CV (dompdf: CSS 2.1 tables/floats only). All text is escaped by Blade. --}}
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
    <style>
        @page { margin: 14mm 14mm 16mm 14mm; }
        body { font-size: 9pt; line-height: 1.45; }
        h1 { font-size: 22pt; }
        h2 { font-size: 11pt; margin: 6mm 0 2.5mm; padding-bottom: 1.2mm; border-bottom: 1px solid {{ $theme['line'] }}; text-transform: uppercase; letter-spacing: 1pt; font-family: 'DejaVu Sans', sans-serif; font-weight: bold; }
        h3 { font-size: 10pt; margin: 0; font-family: 'DejaVu Sans', sans-serif; font-weight: bold; }
        .entry { width: 100%; border-collapse: collapse; margin-bottom: 3mm; page-break-inside: avoid; }
        .entry td { vertical-align: top; padding: 0; }
        .entry .when { width: 30mm; padding-right: 3mm; font-size: 8pt; color: {{ $theme['muted'] }}; }
        .contact td { font-size: 8.5pt; padding: 0.4mm 4mm 0.4mm 0; }
        .skills td { vertical-align: top; padding: 0 0 1.5mm; font-size: 8.5pt; }
        .skills .group { width: 30mm; padding-right: 3mm; font-weight: bold; }
        ul { margin: 1mm 0 0; padding-left: 4mm; }
        li { margin-bottom: 0.6mm; }
    </style>
</head>
<body>
    <div class="footer">
        <span>{{ $doc->ownerName }} — {{ __('Curriculum vitae') }}</span>
        <span style="float: right;">{{ __('Page') }} <span class="page-number"></span></span>
    </div>

    <table style="width: 100%;">
        <tr>
            <td style="vertical-align: top;">
                <h1>{{ $doc->ownerName }}</h1>
                @if ($profile?->headline)
                    <p class="muted" style="font-size: 11pt; margin-top: 1.5mm;">{{ $profile->headline }}</p>
                @endif
                <table class="contact" style="margin-top: 2mm;">
                    <tr>
                        @if ($profile?->location_public)<td>{{ $profile->location_public }}</td>@endif
                        @if ($profile?->public_email)<td>{{ $profile->public_email }}</td>@endif
                        <td class="accent">{{ route('home') }}</td>
                    </tr>
                </table>
            </td>
            @if ($qr = $doc->qrDataUri())
                <td style="width: 24mm; text-align: center; vertical-align: top;">
                    <img src="{{ $qr }}" alt="{{ __('QR code to :url', ['url' => route('home')]) }}" style="width: 22mm; height: 22mm;">
                    <div class="muted" style="font-size: 6.5pt;">{{ __('Scan for portfolio') }}</div>
                </td>
            @endif
            @if ($doc->avatar)
                <td style="width: 26mm; text-align: right; vertical-align: top;">
                    <img src="{{ $doc->avatar->dataUri() }}" alt="{{ $doc->avatar->alt ?: $doc->ownerName }}" style="width: 24mm; height: 24mm; border: 1px solid {{ $theme['line'] }};">
                </td>
            @endif
        </tr>
    </table>

    @if ($profile?->summary)
        <h2>{{ __('Profile') }}</h2>
        <p>{{ $profile->summary }}</p>
    @endif

    @if ($doc->experiences->isNotEmpty())
        <h2>{{ __('Experience') }}</h2>
        @foreach ($doc->experiences as $experience)
            <table class="entry">
                <tr>
                    <td class="when">
                        {{ $experience->start_date->translatedFormat('M Y') }} –
                        {{ $experience->is_current ? __('Present') : $experience->end_date?->translatedFormat('M Y') }}
                    </td>
                    <td>
                        <h3>{{ $experience->title }}</h3>
                        <span class="muted">{{ $experience->company }} · {{ $experience->employment_type->label() }}@if ($experience->location) · {{ $experience->location }}@endif</span>
                        @foreach ($paragraphs($experience->description) as $paragraph)
                            <p style="margin-top: 1mm;">{!! nl2br(e(trim($paragraph))) !!}</p>
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
            </table>
        @endforeach
    @endif

    @if ($doc->skillGroups->isNotEmpty())
        <h2>{{ __('Skills') }}</h2>
        <table class="skills" style="width: 100%;">
            @foreach ($doc->skillGroups as $group)
                <tr>
                    <td class="group">{{ $group->name }}</td>
                    <td>{{ $group->skills->pluck('name')->implode(' · ') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($doc->projects->isNotEmpty())
        <h2>{{ __('Selected projects') }}</h2>
        @foreach ($doc->projects as $project)
            <table class="entry">
                <tr>
                    <td class="when">{{ $project->start_date?->translatedFormat('Y') }}</td>
                    <td>
                        <h3>{{ $project->title }}</h3>
                        @php($meta = collect([$project->role, $doc->client($project), $project->skills->take(5)->pluck('name')->implode(', ')])->filter()->implode(' · '))
                        @if ($meta !== '')<span class="muted">{{ $meta }}</span>@endif
                        @if ($project->excerpt)<p style="margin-top: 1mm;">{{ $project->excerpt }}</p>@endif
                        @if ($project->outcome)<p class="accent">{{ \Illuminate\Support\Str::limit($project->outcome, 220) }}</p>@endif
                    </td>
                </tr>
            </table>
        @endforeach
    @endif

    @if ($doc->educations->isNotEmpty())
        <h2>{{ __('Education') }}</h2>
        @foreach ($doc->educations as $education)
            <table class="entry">
                <tr>
                    <td class="when">{{ $education->start_date?->format('Y') }}@if ($education->start_date) – @endif{{ $education->end_date?->format('Y') ?? ($education->isOngoing() ? __('Present') : '') }}</td>
                    <td>
                        <h3>{{ $education->institution }}</h3>
                        <span class="muted">{{ collect([$education->degree, $education->field_of_study])->filter()->implode(' · ') }}</span>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif

    @if ($doc->certifications->isNotEmpty())
        <h2>{{ __('Certifications') }}</h2>
        @foreach ($doc->certifications as $certification)
            <table class="entry">
                <tr>
                    <td class="when">{{ $certification->issue_date->translatedFormat('M Y') }}</td>
                    <td>
                        <h3>{{ $certification->name }}</h3>
                        <span class="muted">{{ $certification->issuer }}@if ($certification->credential_id) · {{ __('ID: :id', ['id' => $certification->credential_id]) }}@endif</span>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif
</body>
</html>
