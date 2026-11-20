<?php

namespace App\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Freelance = 'freelance';
    case Internship = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => __('Full-time'),
            self::PartTime => __('Part-time'),
            self::Contract => __('Contract'),
            self::Freelance => __('Freelance'),
            self::Internship => __('Internship'),
        };
    }
}
