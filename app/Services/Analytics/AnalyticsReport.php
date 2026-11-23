<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\Project;
use App\Models\Resume;
use App\Support\QrTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregates for the admin analytics page, over the last N days (today included).
 */
class AnalyticsReport
{
    public const RANGES = [7, 30, 90];

    private CarbonImmutable $from;

    public function __construct(private int $days = 30)
    {
        $this->days = in_array($days, self::RANGES, true) ? $days : 30;
        $this->from = CarbonImmutable::today()->subDays($this->days - 1);
    }

    public function days(): int
    {
        return $this->days;
    }

    /**
     * @return array{views: int, visitors: int, cv_downloads: int, qr_visits: int}
     */
    public function totals(): array
    {
        return [
            'views' => $this->views()->count(),
            // The visitor hash rotates daily, so this is the sum of daily unique visitors.
            'visitors' => $this->views()->distinct()->count('visitor_hash'),
            'cv_downloads' => $this->events(AnalyticsEventType::CvDownload)->count(),
            'qr_visits' => $this->views()->where('source', QrTarget::TRACKING_REF)->count(),
        ];
    }

    /**
     * One row per day in the range, zero-filled.
     *
     * @return list<array{date: CarbonImmutable, views: int, visitors: int}>
     */
    public function daily(): array
    {
        $rows = $this->views()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => (string) $row->getAttribute('day'));

        $series = [];

        for ($date = $this->from; $date->lte(CarbonImmutable::today()); $date = $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $series[] = [
                'date' => $date,
                'views' => (int) ($row?->getAttribute('views') ?? 0),
                'visitors' => (int) ($row?->getAttribute('visitors') ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @return Collection<int, array{project: Project, views: int}>
     */
    public function topProjects(int $limit = 10): Collection
    {
        $counts = $this->views()
            ->whereNotNull('project_id')
            ->selectRaw('project_id, COUNT(*) as views')
            ->groupBy('project_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->pluck('views', 'project_id');

        $projects = Project::withTrashed()->whereKey($counts->keys())->get(['id', 'title', 'slug', 'deleted_at'])->keyBy('id');

        return $counts
            ->map(fn ($views, $projectId) => ['project' => $projects->get($projectId), 'views' => (int) $views])
            ->filter(fn (array $row) => $row['project'] !== null)
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, count: int}>
     */
    public function topPages(int $limit = 10): Collection
    {
        return $this->grouped($this->views(), 'path', $limit);
    }

    /**
     * @return Collection<int, array{label: string, count: int}>
     */
    public function referrers(int $limit = 10): Collection
    {
        return $this->grouped($this->views()->whereNotNull('referrer_host'), 'referrer_host', $limit);
    }

    /**
     * Campaign markers such as ?ref=qr.
     *
     * @return Collection<int, array{label: string, count: int}>
     */
    public function sources(int $limit = 10): Collection
    {
        return $this->grouped($this->views()->whereNotNull('source'), 'source', $limit);
    }

    /**
     * @return Collection<int, array{label: string, count: int}>
     */
    public function devices(): Collection
    {
        return $this->grouped($this->views(), 'device', 2);
    }

    /**
     * @return Collection<int, array{resume: Resume|null, count: int}>
     */
    public function cvDownloads(): Collection
    {
        $counts = $this->events(AnalyticsEventType::CvDownload)
            ->selectRaw('resume_id, COUNT(*) as downloads')
            ->groupBy('resume_id')
            ->orderByDesc('downloads')
            ->pluck('downloads', 'resume_id');

        $resumes = Resume::whereKey($counts->keys()->filter())->get()->keyBy('id');

        return $counts->map(fn ($count, $resumeId) => ['resume' => $resumes->get($resumeId), 'count' => (int) $count])->values();
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function views(): Builder
    {
        return $this->events(AnalyticsEventType::PageView);
    }

    /**
     * @return Builder<AnalyticsEvent>
     */
    private function events(AnalyticsEventType $type): Builder
    {
        return AnalyticsEvent::query()->where('type', $type)->where('created_at', '>=', $this->from);
    }

    /**
     * @param  Builder<AnalyticsEvent>  $query
     * @param  'path'|'referrer_host'|'source'|'device'  $column
     * @return Collection<int, array{label: string, count: int}>
     */
    private function grouped(Builder $query, string $column, int $limit): Collection
    {
        // select() escapes the identifier; only the static aggregate is raw SQL.
        return $query->select("{$column} as label")
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->getAttribute('label'), 'count' => (int) $row->getAttribute('total')])
            ->values()
            ->toBase();
    }
}
