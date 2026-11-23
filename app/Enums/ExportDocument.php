<?php

namespace App\Enums;

enum ExportDocument: string
{
    /** Profile, projects and career history. */
    case Portfolio = 'portfolio';

    /** Compact professional CV (1–2 pages). */
    case Cv = 'cv';

    /** One in-depth section (or slide set) per selected project. */
    case CaseStudy = 'case_study';

    public function label(): string
    {
        return match ($this) {
            self::Portfolio => __('Full portfolio'),
            self::Cv => __('Professional CV'),
            self::CaseStudy => __('Project case study'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Portfolio => __('Introduction, projects with galleries, experience and education.'),
            self::Cv => __('A compact one- to two-page CV with experience, skills, education and a few selected projects.'),
            self::CaseStudy => __('An in-depth presentation of the projects you select: challenge, approach, outcome and visuals.'),
        };
    }

    public function supports(ExportFormat $format): bool
    {
        return $this !== self::Cv || $format === ExportFormat::Pdf;
    }

    /**
     * @return list<ExportFormat>
     */
    public function formats(): array
    {
        return array_values(array_filter(ExportFormat::cases(), fn (ExportFormat $format): bool => $this->supports($format)));
    }
}
