<?php

namespace App\Support;

use App\Models\Project;

/**
 * Pages a QR code may point to. Only our own public pages are allowed, so the
 * generator can never be used to encode arbitrary URLs.
 */
final class QrTarget
{
    /** Query marker counted as a "QR scan" source in analytics. */
    public const TRACKING_REF = 'qr';

    /**
     * @return array<string, string> target key => label
     */
    public static function options(): array
    {
        return [
            'home' => __('Home page'),
            'about' => __('About'),
            'projects' => __('Projects'),
            'cv' => __('CV page'),
            'contact' => __('Contact'),
            'project' => __('A specific project'),
        ];
    }

    /**
     * Absolute URL for a target, or null when the target is unknown or the project is not public.
     */
    public static function url(string $target, ?int $projectId = null, bool $track = true): ?string
    {
        $query = $track ? ['ref' => self::TRACKING_REF] : [];

        return match ($target) {
            'home' => route('home', $query),
            'about' => route('about', $query),
            'projects' => route('projects.index', $query),
            'cv' => route('cv', $query),
            'contact' => route('contact', $query),
            'project' => ($project = Project::query()->published()->find($projectId))
                ? route('projects.show', ['slug' => $project->slug, ...$query])
                : null,
            default => null,
        };
    }
}
