<?php

namespace App\Services\Export;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Everything a renderer needs, already filtered to what may be exported.
 */
final readonly class PortfolioDocument
{
    /**
     * @param  Collection<int, Project>  $projects
     * @param  array<int, ExportImage|null>  $covers  keyed by project id
     * @param  array<int, list<ExportImage>>  $galleries  keyed by project id
     * @param  Collection<int, Experience>  $experiences
     * @param  Collection<int, Education>  $educations
     * @param  Collection<int, Certification>  $certifications
     */
    public function __construct(
        public ExportOptions $options,
        public string $ownerName,
        public ?Profile $profile,
        public ?ExportImage $avatar,
        public Collection $projects,
        public array $covers,
        public array $galleries,
        public Collection $experiences,
        public Collection $educations,
        public Collection $certifications,
    ) {}

    public function title(): string
    {
        return __('Portfolio — :name', ['name' => $this->ownerName]);
    }

    public function client(Project $project): ?string
    {
        return $project->displayClient($this->options->includeConfidential);
    }

    public function cover(Project $project): ?ExportImage
    {
        return $this->covers[$project->id] ?? null;
    }

    /**
     * @return list<ExportImage>
     */
    public function gallery(Project $project): array
    {
        return $this->galleries[$project->id] ?? [];
    }
}
