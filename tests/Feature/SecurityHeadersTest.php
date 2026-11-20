<?php

use App\Enums\MediaVisibility;
use App\Models\User;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function cspOf($response): string
{
    return (string) $response->headers->get('Content-Security-Policy');
}

test('public pages get a strict nonce-based CSP without eval', function () {
    $response = $this->get(route('home'))->assertOk();
    $csp = cspOf($response);

    expect($csp)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'self'")
        ->toContain("form-action 'self'")
        ->toContain('frame-src https://www.youtube-nocookie.com https://player.vimeo.com')
        ->not->toContain("'unsafe-eval'")
        ->not->toContain("script-src 'self' 'unsafe-inline'");

    preg_match("/'nonce-([^']+)'/", $csp, $match);
    expect($match[1] ?? '')->not->toBe('');

    // Every script tag on the page carries the same nonce.
    preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$match[1].'"');
    }
});

test('each request gets a fresh nonce', function () {
    $first = cspOf($this->get(route('home')));
    $second = cspOf($this->get(route('home')));

    expect($first)->not->toBe($second);
});

test('Livewire pages allow eval for Alpine, public pages do not', function () {
    expect(cspOf($this->get(route('login'))))->toContain("'unsafe-eval'");
    expect(cspOf($this->actingAs(User::factory()->owner()->create())->get(route('admin.dashboard'))))->toContain("'unsafe-eval'");
    expect(cspOf($this->get(route('projects.index'))))->not->toContain("'unsafe-eval'");
});

test('file responses keep their own stricter policy', function () {
    Storage::fake('local');
    $media = app(MediaUploader::class)->store(UploadedFile::fake()->image('a.jpg', 400, 400), ['alt_text' => 'a'], MediaVisibility::Public, User::factory()->owner()->create());

    expect(cspOf($this->get($media->url('thumb'))))->toBe("default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
});

test('HSTS is only sent in production over HTTPS', function () {
    $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

    app()->detectEnvironment(fn () => 'production');

    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    expect(cspOf($this->get('https://localhost/')))->toEndWith('upgrade-insecure-requests');
});
