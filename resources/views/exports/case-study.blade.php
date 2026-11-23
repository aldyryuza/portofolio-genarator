{{-- Project case studies (dompdf: CSS 2.1 only). One section per project; all text escaped by Blade. --}}
@php
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
        <span>{{ $doc->ownerName }} — {{ __('Case study') }}</span>
        <span style="float: right;">{{ __('Page') }} <span class="page-number"></span></span>
    </div>

    @forelse ($doc->projects as $project)
        {{-- Opening page --}}
        <div class="page">
            <p class="eyebrow" style="margin-top: 30mm;">{{ __('Case study') }}@if ($project->category) · {{ $project->category->name }}@endif</p>
            <h1>{{ $project->title }}</h1>
            @if ($project->excerpt)
                <p class="muted" style="font-size: 13pt; margin-top: 4mm;">{{ $project->excerpt }}</p>
            @endif

            @php
                $period = collect([
                    $project->start_date?->translatedFormat('M Y'),
                    $project->start_date ? ($project->end_date?->translatedFormat('M Y') ?? __('Ongoing')) : null,
                ])->filter()->implode(' – ');
            @endphp
            <table class="meta" style="margin-top: 10mm;">
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

            <p class="muted" style="margin-top: 6mm;">{{ $doc->ownerName }}@if ($doc->profile?->headline) · {{ $doc->profile->headline }}@endif</p>
        </div>

        {{-- Story --}}
        <div class="page">
            @foreach ([__('Overview') => $project->summary, __('The challenge') => $project->challenge, __('Approach & responsibilities') => $project->solution, __('Outcome') => $project->outcome] as $heading => $text)
                @if (filled($text))
                    <div @class(['highlight' => $heading === __('Outcome')]) style="margin-bottom: 4mm;">
                        <h2>{{ $heading }}</h2>
                        @foreach ($paragraphs($text) as $paragraph)
                            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($doc->options->includeConfidential && filled($project->confidential_notes))
                <div class="highlight">
                    <h2>{{ __('Confidential notes') }}</h2>
                    @foreach ($paragraphs($project->confidential_notes) as $paragraph)
                        <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                    @endforeach
                </div>
            @endif

            @if ($project->videos->isNotEmpty())
                <h3>{{ __('Video') }}</h3>
                <ul>
                    @foreach ($project->videos as $video)
                        <li>{{ $video->title ?? $video->provider->label() }} — <span class="accent">{{ $video->watchUrl() }}</span></li>
                    @endforeach
                </ul>
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
            <h2>{{ __('Case study') }}</h2>
            <p class="muted">{{ __('No projects match the selected export options.') }}</p>
        </div>
    @endforelse
</body>
</html>
