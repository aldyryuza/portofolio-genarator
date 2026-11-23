<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Middleware\TrackPageView;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectSlugRedirect;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'skill' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        // Unknown or inactive filters are a 404, not an empty page.
        $category = filled($filters['category'] ?? null)
            ? Category::where('slug', $filters['category'])->where('is_active', true)->firstOrFail()
            : null;
        $skill = filled($filters['skill'] ?? null)
            ? Skill::where('slug', $filters['skill'])->where('is_published', true)->firstOrFail()
            : null;
        $search = trim($filters['q'] ?? '');
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        $projects = Project::query()
            ->published()
            ->with(['cover', 'category', 'skills' => fn ($query) => $query->where('is_published', true)])
            ->when($category, fn ($query) => $query->where('category_id', $category->id))
            ->when($skill, fn ($query) => $query->whereHas('skills', fn ($q) => $q->whereKey($skill->id)))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('excerpt', 'like', $like)))
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        return view('site.projects.index', [
            'projects' => $projects,
            'categories' => Category::query()->where('is_active', true)
                ->whereHas('projects', fn ($query) => $query->published())
                ->ordered()
                ->get(),
            'skills' => Skill::query()->where('is_published', true)
                ->whereHas('projects', fn ($query) => $query->published())
                ->ordered()
                ->get(),
            'activeCategory' => $category,
            'activeSkill' => $skill,
            'search' => $search,
            'isFiltered' => $category !== null || $skill !== null || $search !== '',
        ]);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $project = Project::where('slug', $slug)->first();

        if ($project === null) {
            return $this->redirectFromOldSlug($slug);
        }

        // Drafts are visible to the owner only; everyone else gets a plain 404.
        abort_unless(Gate::allows('view', $project), 404);

        if ($project->isPublished()) {
            // Lets the page-view tracker attribute this visit to the project.
            $request->attributes->set(TrackPageView::PROJECT_ATTRIBUTE, $project->id);
        }

        return $this->render($project, isPreview: ! $project->isPublished());
    }

    /**
     * Shareable, time-limited preview (route uses the `signed` middleware).
     */
    public function preview(Project $project): View
    {
        return $this->render($project, isPreview: true);
    }

    private function redirectFromOldSlug(string $slug): RedirectResponse
    {
        $redirect = ProjectSlugRedirect::with('project')->where('slug', $slug)->first();

        abort_unless($redirect?->project !== null && $redirect->project->isPublished(), 404);

        return redirect()->route('projects.show', $redirect->project->slug, 301);
    }

    private function render(Project $project, bool $isPreview): View
    {
        $project->load([
            'cover',
            'category',
            'skills' => fn ($query) => $query->where('is_published', true)->ordered(),
            'tags',
            'mediaItems.media',
            'videos',
        ]);

        return view('site.projects.show', [
            'project' => $project,
            // Private and confidential images stay hidden, even in previews, so previews match the live page.
            'gallery' => $project->visibleMediaItems(),
            'cover' => $project->cover?->isPublic() ? $project->cover : null,
            'isPreview' => $isPreview,
        ]);
    }
}
