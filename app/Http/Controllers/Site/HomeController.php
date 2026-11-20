<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\Project;
use App\Models\SkillGroup;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const FEATURED_LIMIT = 6;

    public function __invoke(): View
    {
        $featured = Project::query()
            ->published()
            ->where('is_featured', true)
            ->with(['cover', 'category', 'skills' => fn ($query) => $query->where('is_published', true)])
            ->ordered()
            ->limit(self::FEATURED_LIMIT)
            ->get();

        // Fall back to the first published projects when nothing is featured yet.
        if ($featured->isEmpty()) {
            $featured = Project::query()
                ->published()
                ->with(['cover', 'category', 'skills' => fn ($query) => $query->where('is_published', true)])
                ->ordered()
                ->limit(3)
                ->get();
        }

        return view('site.home', [
            'featured' => $featured,
            'skillGroups' => SkillGroup::withPublishedSkills(),
            'experiences' => Experience::query()->published()->chronological()->limit(3)->get(),
        ]);
    }
}
