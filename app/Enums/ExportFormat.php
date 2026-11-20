<?php

namespace App\Enums;

enum ExportFormat: string
{
    case Pdf = 'pdf';
    case Pptx = 'pptx';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Pptx => 'PowerPoint (PPTX)',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Pptx => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        };
    }
}
