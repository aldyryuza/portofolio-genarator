<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case OpenToWork = 'open_to_work';
    case OpenToProjects = 'open_to_projects';
    case NotAvailable = 'not_available';

    public function label(): string
    {
        return match ($this) {
            self::OpenToWork => __('Open to work'),
            self::OpenToProjects => __('Open to projects'),
            self::NotAvailable => __('Not available'),
        };
    }
}
