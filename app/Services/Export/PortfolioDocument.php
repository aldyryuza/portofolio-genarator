<?php

namespace App\Services\Export;

use App\Enums\ExportDocument;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SkillGroup;
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
     * @param  Collection<int, SkillGroup>  $skillGroups  CV only
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
        public Collection $skillGroups = new Collection,
        public ?string $qrPng = null,
    ) {}

    /**
     * QR code linking to the site (tracked as a "qr" visit), as a data URI for PDFs.
     */
    public function qrDataUri(): ?string
    {
        return $this->qrPng === null ? null : 'data:image/png;base64,'.base64_encode($this->qrPng);
    }

    public function title(): string
    {
        return match ($this->options->document) {
            ExportDocument::Portfolio => __('Portfolio — :name', ['name' => $this->ownerName]),
            ExportDocument::Cv => __('Curriculum vitae — :name', ['name' => $this->ownerName]),
            ExportDocument::CaseStudy => $this->projects->count() === 1
                ? __('Case study — :project', ['project' => $this->projects->first()?->title])
                : __('Case studies — :name', ['name' => $this->ownerName]),
        };
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
