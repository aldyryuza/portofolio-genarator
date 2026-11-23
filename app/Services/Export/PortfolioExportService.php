<?php

namespace App\Services\Export;

use App\Enums\ExportDocument;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Project;
use App\Models\SkillGroup;
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
        $isCv = $options->document === ExportDocument::Cv;
        $projects = $this->projects($options, $isCv ? min($options->maxProjects, ExportOptions::CV_MAX_PROJECTS) : $options->maxProjects);
        $covers = [];
        $galleries = [];

        foreach ($projects as $project) {
            $covers[$project->id] = $isCv ? null : ExportImage::fromMedia($project->cover);
            $galleries[$project->id] = $options->includeGallery && ! $isCv
                ? array_values($project->visibleMediaItems($options->includeConfidential)
                    ->map(fn ($item) => ExportImage::fromMedia($item->media))
                    ->filter()
                    ->take(self::GALLERY_LIMIT)
                    ->all())
                : [];
        }

        $profile = $this->owner->profile();
        // A CV is always about the person and their career; the section toggles apply to the other documents.
        $withProfile = $isCv || $options->includeProfile;
        $withExperience = $isCv || $options->includeExperience;
        $withEducation = $isCv || $options->includeEducation;

        return new PortfolioDocument(
            options: $options,
            ownerName: $this->owner->name(),
            profile: $withProfile ? $profile : null,
            avatar: $withProfile ? ExportImage::fromMedia($profile?->avatar, 'thumb') : null,
            projects: $projects,
            covers: $covers,
            galleries: $galleries,
            experiences: $withExperience ? Experience::query()->published()->chronological()->get() : new Collection,
            educations: $withEducation ? Education::query()->published()->chronological()->get() : new Collection,
            certifications: $withEducation ? Certification::query()->published()->chronological()->get() : new Collection,
            skillGroups: $isCv ? SkillGroup::withPublishedSkills() : new Collection,
        );
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(ExportOptions $options, int $limit): Collection
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
                'videos',
            ])
            ->ordered()
            ->limit($limit)
            ->get()
            ->toBase();
    }
}
