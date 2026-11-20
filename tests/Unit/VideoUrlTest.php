<?php

use App\Enums\VideoProvider;
use App\Support\VideoUrl;

test('accepts supported YouTube and Vimeo links', function (string $url, VideoProvider $provider, string $id) {
    expect(VideoUrl::parse($url))->toBe(['provider' => $provider, 'id' => $id]);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube watch with extra params' => ['https://youtube.com/watch?v=dQw4w9WgXcQ&t=42s', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'youtube with whitespace' => ['  https://youtu.be/dQw4w9WgXcQ  ', VideoProvider::YouTube, 'dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/76979871', VideoProvider::Vimeo, '76979871'],
    'vimeo player' => ['https://player.vimeo.com/video/76979871', VideoProvider::Vimeo, '76979871'],
]);

test('rejects anything that is not a plain supported link', function (string $url) {
    expect(VideoUrl::parse($url))->toBeNull();
})->with([
    'iframe html' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
    'javascript scheme' => ['javascript:alert(1)//https://youtu.be/dQw4w9WgXcQ'],
    'data scheme' => ['data:text/html,<script>alert(1)</script>'],
    'lookalike host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'],
    'subdomain of attacker' => ['https://evil.test/youtu.be/dQw4w9WgXcQ'],
    'other provider' => ['https://dailymotion.com/video/x7tgad0'],
    'youtube id wrong length' => ['https://youtu.be/short'],
    'youtube id with path injection' => ['https://youtu.be/dQw4w9WgXcQ/../../x'],
    'youtube channel page' => ['https://www.youtube.com/@somechannel'],
    'youtube array param' => ['https://www.youtube.com/watch?v[]=dQw4w9WgXcQ'],
    'vimeo non-numeric' => ['https://vimeo.com/channels/staffpicks'],
    'no scheme' => ['youtu.be/dQw4w9WgXcQ'],
    'empty' => [''],
]);

test('embed urls are built only from the validated id', function () {
    expect(VideoProvider::YouTube->embedUrl('dQw4w9WgXcQ'))->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->and(VideoProvider::Vimeo->embedUrl('76979871'))->toBe('https://player.vimeo.com/video/76979871?dnt=1');
});
