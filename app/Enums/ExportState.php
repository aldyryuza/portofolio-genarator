<?php

namespace App\Enums;

enum ExportState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Completed => __('Ready'),
            self::Failed => __('Failed'),
        };
    }
}
