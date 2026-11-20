<?php

namespace App\Services\Export;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Project;
use App\Support\SiteOwner;
use Illuminate\Support\Collection;

/**
 * Composes the data for an export. Only public images are used, only published
 * projects unless the owner includes drafts, and confidential details (hidden
 * client names, confidential images and notes) only when explicitly requested.
 */
class PortfolioExportService
{
    private const GALLERY_LIMIT = 4;

    public function __construct(private SiteOwner $owner) {}

    public function compose(ExportOptions $options): PortfolioDocument
    {
        $projects = $this->projects($options);
        $covers = [];
        $galleries = [];

        foreach ($projects as $project) {
            $covers[$project->id] = ExportImage::fromMedia($project->cover);
            $galleries[$project->id] = $options->includeGallery
                ? array_values($project->visibleMediaItems($options->includeConfidential)
                    ->map(fn ($item) => ExportImage::fromMedia($item->media))
                    ->filter()
                    ->take(self::GALLERY_LIMIT)
                    ->all())
                : [];
        }

        $profile = $this->owner->profile();

        return new PortfolioDocument(
            options: $options,
            ownerName: $this->owner->name(),
            profile: $options->includeProfile ? $profile : null,
            avatar: $options->includeProfile ? ExportImage::fromMedia($profile?->avatar, 'thumb') : null,
            projects: $projects,
            covers: $covers,
            galleries: $galleries,
            experiences: $options->includeExperience ? Experience::query()->published()->chronological()->get() : new Collection,
            educations: $options->includeEducation ? Education::query()->published()->chronological()->get() : new Collection,
            certifications: $options->includeEducation ? Certification::query()->published()->chronological()->get() : new Collection,
        );
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(ExportOptions $options): Collection
    {
        return Project::query()
            ->when(! $options->includeDrafts, fn ($query) => $query->published())
            ->when($options->scope === 'selected', fn ($query) => $query->whereKey($options->projectIds))
            ->when($options->scope === 'category', fn ($query) => $query->where('category_id', $options->categoryId))
            ->with([
                'cover',
                'category',
                'skills' => fn ($query) => $query->where('is_published', true)->ordered(),
                'mediaItems.media',
            ])
            ->ordered()
            ->limit($options->maxProjects)
            ->get()
            ->toBase();
    }
}
