<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case LinkedIn = 'linkedin';
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case X = 'x';
    case Dribbble = 'dribbble';
    case Behance = 'behance';
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::X => 'X',
            self::Dribbble => 'Dribbble',
            self::Behance => 'Behance',
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::Website => __('Website'),
            self::Other => __('Other'),
        };
    }
}
