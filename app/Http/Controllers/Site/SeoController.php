<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Lists published content only; drafts, scheduled and trashed projects never appear.
     */
    public function sitemap(): Response
    {
        $pages = collect(['home', 'about', 'projects.index', 'experience', 'education', 'cv', 'contact'])
            ->map(fn (string $route) => ['loc' => route($route), 'lastmod' => null]);

        $projects = Project::query()->published()->ordered()->get(['slug', 'updated_at'])
            ->map(fn (Project $project) => [
                'loc' => route('projects.show', $project->slug),
                'lastmod' => $project->updated_at?->toAtomString(),
            ]);

        return response()
            ->view('site.sitemap', ['urls' => $pages->concat($projects)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Non-production environments ask crawlers to stay away entirely.
     */
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /preview/', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
