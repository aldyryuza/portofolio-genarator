<?php

namespace App\Http\Middleware;

use App\Enums\AnalyticsEventType;
use App\Services\Analytics\AnalyticsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a page view for public pages after the response has been sent,
 * so analytics never slows a page down.
 */
class TrackPageView
{
    /** Set by controllers to attribute a view to a project. */
    public const PROJECT_ATTRIBUTE = 'analytics.project_id';

    public function __construct(private AnalyticsRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET')
            || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        $projectId = $request->attributes->get(self::PROJECT_ATTRIBUTE);

        $this->recorder->record(AnalyticsEventType::PageView, $request, [
            'project_id' => is_int($projectId) ? $projectId : null,
        ]);
    }
}
