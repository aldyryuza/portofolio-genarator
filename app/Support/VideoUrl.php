<?php

namespace App\Support;

use App\Enums\VideoProvider;

/**
 * Parses a pasted YouTube/Vimeo link into a provider and video id.
 * Anything else (other hosts, iframes, javascript: URLs) is rejected.
 */
final class VideoUrl
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtube-nocookie.com'];

    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    /**
     * @return array{provider: VideoProvider, id: string}|null
     */
    public static function parse(string $url): ?array
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            parse_str($parts['query'] ?? '', $query);

            $id = match (true) {
                $host === 'youtu.be' => ltrim($path, '/'),
                $path === '/watch' => is_string($query['v'] ?? null) ? $query['v'] : '',
                default => preg_match('#^/(?:embed|shorts|live)/([^/]+)$#', $path, $m) ? $m[1] : '',
            };

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['provider' => VideoProvider::YouTube, 'id' => $id] : null;
        }

        if (in_array($host, self::VIMEO_HOSTS, true)) {
            $pattern = $host === 'player.vimeo.com' ? '#^/video/(\d{1,12})$#' : '#^/(\d{1,12})/?$#';

            return preg_match($pattern, $path, $m) ? ['provider' => VideoProvider::Vimeo, 'id' => $m[1]] : null;
        }

        return null;
    }
}
