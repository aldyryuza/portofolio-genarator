<?php

namespace App\Enums;

enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Vimeo => 'Vimeo',
        };
    }

    /**
     * Embed URL built from a validated id; never from user-supplied HTML.
     */
    public function embedUrl(string $videoId): string
    {
        return match ($this) {
            self::YouTube => 'https://www.youtube-nocookie.com/embed/'.rawurlencode($videoId),
            self::Vimeo => 'https://player.vimeo.com/video/'.rawurlencode($videoId).'?dnt=1',
        };
    }

    public function watchUrl(string $videoId): string
    {
        return match ($this) {
            self::YouTube => 'https://www.youtube.com/watch?v='.rawurlencode($videoId),
            self::Vimeo => 'https://vimeo.com/'.rawurlencode($videoId),
        };
    }
}
