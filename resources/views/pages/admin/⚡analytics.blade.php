<?php

use App\Services\Analytics\AnalyticsReport;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Analytics')] class extends Component
{
    #[Url(except: 30)]
    public int $range = 30;

    public function mount(): void
    {
        abort_unless(Gate::allows('access-admin'), 403);
    }

    #[Computed]
    public function report(): AnalyticsReport
    {
        return new AnalyticsReport($this->range);
    }
};
?>

@php
    $report = $this->report;
    $totals = $report->totals();
    $daily = $report->daily();
    $peak = max(1, max(array_column($daily, 'views')));
    $tiles = [
        ['label' => __('Visitors'), 'value' => $totals['visitors'], 'hint' => __('unique per day')],
        ['label' => __('Page views'), 'value' => $totals['views'], 'hint' => null],
        ['label' => __('CV downloads'), 'value' => $totals['cv_downloads'], 'hint' => null],
        ['label' => __('Visits from QR codes'), 'value' => $totals['qr_visits'], 'hint' => null],
    ];
@endphp

<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Analytics') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Cookie-less and anonymous: no IP addresses are stored, and bots, your own visits and visitors with Do-Not-Track are not counted.') }}</p>
        </div>

        {{-- Time range: one control, above everything it filters. --}}
        <div class="inline-flex rounded-md border border-line-strong bg-surface p-0.5" role="radiogroup" aria-label="{{ __('Time range') }}">
            @foreach (\App\Services\Analytics\AnalyticsReport::RANGES as $days)
                <label wire:key="range-{{ $days }}" @class(['cursor-pointer rounded px-3 py-1.5 text-sm', 'bg-accent text-accent-ink' => $range === $days, 'text-ink-muted hover:text-ink' => $range !== $days])>
                    <input type="radio" wire:model.live="range" value="{{ $days }}" class="sr-only">
                    {{ __(':days days', ['days' => $days]) }}
                </label>
            @endforeach
        </div>
    </div>

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tiles as $tile)
            <div class="card" wire:key="tile-{{ $loop->index }}">
                <dt class="text-sm text-ink-muted">{{ $tile['label'] }}</dt>
                <dd class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($tile['value']) }}</dd>
                @if ($tile['hint'])<dd class="text-xs text-ink-muted">{{ $tile['hint'] }}</dd>@endif
            </div>
        @endforeach
    </dl>

    <section class="card" aria-labelledby="daily-heading">
        <h2 id="daily-heading" class="text-base font-semibold">{{ __('Page views per day') }}</h2>

        @if ($totals['views'] === 0)
            <p class="mt-3 text-sm text-ink-muted">{{ __('No visits recorded in this period yet.') }}</p>
        @else
            <div class="mt-4 grid grid-cols-[auto_1fr] gap-x-2">
                {{-- Recessive y-axis: peak and zero only. --}}
                <div class="flex h-48 flex-col justify-between text-right text-xs text-ink-muted tabular-nums" aria-hidden="true">
                    <span>{{ $peak }}</span>
                    <span>0</span>
                </div>
                <div class="relative h-48 border-b border-line">
                    <div class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-line" aria-hidden="true"></div>
                    <ol class="flex h-full items-end gap-0.5" aria-label="{{ __('Page views per day') }}">
                        @foreach ($daily as $day)
                            @php($label = __(':date: :views views, :visitors visitors', ['date' => $day['date']->translatedFormat('D j M'), 'views' => $day['views'], 'visitors' => $day['visitors']]))
                            {{-- Hit target is the full column height; the bar itself is thin. --}}
                            <li wire:key="day-{{ $day['date']->toDateString() }}" tabindex="0" aria-label="{{ $label }}"
                                class="group relative flex h-full flex-1 items-end justify-center focus-visible:outline-2 focus-visible:outline-accent">
                                <span class="w-full max-w-6 rounded-t-sm bg-accent transition-opacity group-hover:opacity-80 group-focus-visible:opacity-80"
                                    style="height: {{ $day['views'] === 0 ? 0 : max(2, round($day['views'] / $peak * 100, 2)) }}%"></span>
                                <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md border border-line bg-surface px-2 py-1 text-xs whitespace-nowrap text-ink shadow-md group-hover:block group-focus-visible:block">
                                    <span class="block font-medium">{{ $day['date']->translatedFormat('D j M') }}</span>
                                    <span class="tabular-nums">{{ trans_choice(':count view|:count views', $day['views']) }} · {{ trans_choice(':count visitor|:count visitors', $day['visitors']) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div></div>
                <div class="mt-1 flex justify-between text-xs text-ink-muted" aria-hidden="true">
                    <span>{{ $daily[0]['date']->translatedFormat('j M') }}</span>
                    <span>{{ end($daily)['date']->translatedFormat('j M') }}</span>
                </div>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-accent">{{ __('Show as table') }}</summary>
                <div class="mt-2 max-h-64 overflow-y-auto">
                    <table class="w-full text-left">
                        <thead class="text-ink-muted"><tr><th scope="col" class="py-1">{{ __('Date') }}</th><th scope="col" class="py-1 text-right">{{ __('Page views') }}</th><th scope="col" class="py-1 text-right">{{ __('Visitors') }}</th></tr></thead>
                        <tbody class="divide-y divide-line tabular-nums">
                            @foreach (array_reverse($daily) as $day)
                                <tr wire:key="row-{{ $day['date']->toDateString() }}"><td class="py-1">{{ $day['date']->translatedFormat('D j M Y') }}</td><td class="py-1 text-right">{{ $day['views'] }}</td><td class="py-1 text-right">{{ $day['visitors'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card" aria-labelledby="projects-heading">
            <h2 id="projects-heading" class="text-base font-semibold">{{ __('Popular projects') }}</h2>
            @php($projects = $report->topProjects())
            @if ($projects->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">{{ __('No project visits yet.') }}</p>
            @else
                @php($projectPeak = max(1, $projects->max('views')))
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($projects as $row)
                        <li wire:key="project-{{ $row['project']->id }}">
                            <div class="flex justify-between gap-3">
                                <span class="truncate">{{ $row['project']->title }}@if ($row['project']->trashed()) <span class="text-ink-muted">({{ __('deleted') }})</span>@endif</span>
                                <span class="tabular-nums text-ink-muted">{{ $row['views'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-paper" aria-hidden="true"><div class="h-1.5 rounded-full bg-accent" style="width: {{ round($row['views'] / $projectPeak * 100, 2) }}%"></div></div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="card" aria-labelledby="cv-heading">
            <h2 id="cv-heading" class="text-base font-semibold">{{ __('CV downloads') }}</h2>
            @php($downloads = $report->cvDownloads())
            @if ($downloads->isEmpty())
                <p class="mt-3 text-sm text-ink-muted">{{ __('No CV downloads yet.') }}</p>
            @else
                <table class="mt-3 w-full text-left text-sm">
                    <thead class="sr-only"><tr><th scope="col">{{ __('Version') }}</th><th scope="col">{{ __('Downloads') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($downloads as $row)
                            <tr wire:key="cv-{{ $loop->index }}">
                                <td class="py-1.5">{{ $row['resume'] ? $row['resume']->title.' · '.strtoupper($row['resume']->language).' · '.$row['resume']->version : __('Deleted version') }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ $row['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @foreach ([
            ['id' => 'pages', 'title' => __('Top pages'), 'rows' => $report->topPages(), 'empty' => __('No visits yet.')],
            ['id' => 'referrers', 'title' => __('Referring sites'), 'rows' => $report->referrers(), 'empty' => __('No visits from other sites yet.')],
            ['id' => 'sources', 'title' => __('Campaigns (?ref=…)'), 'rows' => $report->sources(), 'empty' => __('No tagged visits yet. QR codes add ?ref=qr.')],
            ['id' => 'devices', 'title' => __('Devices'), 'rows' => $report->devices(), 'empty' => __('No visits yet.')],
        ] as $list)
            <section class="card" aria-labelledby="{{ $list['id'] }}-heading" wire:key="list-{{ $list['id'] }}">
                <h2 id="{{ $list['id'] }}-heading" class="text-base font-semibold">{{ $list['title'] }}</h2>
                @if ($list['rows']->isEmpty())
                    <p class="mt-3 text-sm text-ink-muted">{{ $list['empty'] }}</p>
                @else
                    <table class="mt-3 w-full text-left text-sm">
                        <thead class="sr-only"><tr><th scope="col">{{ $list['title'] }}</th><th scope="col">{{ __('Count') }}</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($list['rows'] as $row)
                                <tr wire:key="{{ $list['id'] }}-{{ $loop->index }}">
                                    <td class="max-w-0 truncate py-1.5 font-mono text-xs" title="{{ $row['label'] }}">{{ $row['label'] }}</td>
                                    <td class="w-16 py-1.5 text-right tabular-nums">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    </div>
</div>
