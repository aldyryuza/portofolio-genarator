<?php

use App\Models\Project;

test('the sitemap lists public pages and published projects only', function () {
    $live = Project::factory()->published()->create(['slug' => 'live-project']);
    Project::factory()->create(['slug' => 'draft-project']);
    Project::factory()->published()->create(['slug' => 'scheduled-project', 'published_at' => now()->addDay()]);
    tap(Project::factory()->published()->create(['slug' => 'trashed-project']))->delete();

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('projects.show', $live->slug))
        ->assertSee(route('about'))
        ->assertDontSee('draft-project')
        ->assertDontSee('scheduled-project')
        ->assertDontSee('trashed-project')
        ->assertDontSee('/admin');

    expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
});

test('robots.txt blocks everything outside production', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /')
        ->assertDontSee('Sitemap:');
});

test('robots.txt points to the sitemap in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/robots.txt')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.route('sitemap'));
});

test('crawler endpoints are stateless', function (string $path) {
    $response = $this->get($path)->assertOk();

    expect($response->headers->getCookies())->toBe([]);
})->with(['/robots.txt', '/sitemap.xml']);
