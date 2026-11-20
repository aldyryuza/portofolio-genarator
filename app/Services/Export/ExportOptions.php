<?php

namespace App\Services\Export;

use App\Enums\ExportFormat;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validated, immutable description of what an export should contain.
 */
final readonly class ExportOptions
{
    public const SCOPES = ['all', 'selected', 'category'];

    public const TEMPLATES = ['light', 'dark'];

    public const LOCALES = ['en', 'id'];

    public const MAX_PROJECTS = 30;

    /**
     * @param  list<int>  $projectIds
     */
    public function __construct(
        public ExportFormat $format,
        public string $scope = 'all',
        public array $projectIds = [],
        public ?int $categoryId = null,
        public bool $includeDrafts = false,
        public bool $includeProfile = true,
        public bool $includeExperience = true,
        public bool $includeEducation = true,
        public bool $includeGallery = true,
        public bool $includeConfidential = false,
        public int $maxProjects = 12,
        public string $template = 'light',
        public string $locale = 'en',
    ) {}

    /**
     * Validation rules for the array form produced by toArray().
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'scope' => ['required', Rule::in(self::SCOPES)],
            'project_ids' => ['array', 'max:'.self::MAX_PROJECTS, 'required_if:scope,selected'],
            'project_ids.*' => ['integer', 'distinct', Rule::exists(Project::class, 'id')->whereNull('deleted_at')],
            'category_id' => ['nullable', 'integer', 'required_if:scope,category', Rule::exists(Category::class, 'id')],
            'include_drafts' => ['boolean'],
            'include_profile' => ['boolean'],
            'include_experience' => ['boolean'],
            'include_education' => ['boolean'],
            'include_gallery' => ['boolean'],
            'include_confidential' => ['boolean'],
            'max_projects' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PROJECTS],
            'template' => ['required', Rule::in(self::TEMPLATES)],
            'locale' => ['required', Rule::in(self::LOCALES)],
        ];
    }

    /**
     * Build from untrusted input (form or stored preset), validating it first.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $data = Validator::make($input, self::rules())->validate();

        return new self(
            format: ExportFormat::from($data['format']),
            scope: $data['scope'],
            projectIds: array_values(array_map('intval', $data['project_ids'] ?? [])),
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            includeDrafts: (bool) ($data['include_drafts'] ?? false),
            includeProfile: (bool) ($data['include_profile'] ?? true),
            includeExperience: (bool) ($data['include_experience'] ?? true),
            includeEducation: (bool) ($data['include_education'] ?? true),
            includeGallery: (bool) ($data['include_gallery'] ?? true),
            includeConfidential: (bool) ($data['include_confidential'] ?? false),
            maxProjects: (int) $data['max_projects'],
            template: $data['template'],
            locale: $data['locale'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'format' => $this->format->value,
            'scope' => $this->scope,
            'project_ids' => $this->projectIds,
            'category_id' => $this->categoryId,
            'include_drafts' => $this->includeDrafts,
            'include_profile' => $this->includeProfile,
            'include_experience' => $this->includeExperience,
            'include_education' => $this->includeEducation,
            'include_gallery' => $this->includeGallery,
            'include_confidential' => $this->includeConfidential,
            'max_projects' => $this->maxProjects,
            'template' => $this->template,
            'locale' => $this->locale,
        ];
    }
}
