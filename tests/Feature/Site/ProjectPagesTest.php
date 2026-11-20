<?php

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\ProjectVideo;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('published projects are shown with their case study', function () {
    $project = Project::factory()->published()->create([
        'title' => '[DEMO] Booking Platform',
        'challenge' => 'The challenge text',
        'outcome' => 'The outcome text',
        'meta_description' => 'Meta description text',
    ]);

    $this->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertSee('[DEMO] Booking Platform')
        ->assertSee('The challenge text')
        ->assertSee('The outcome text')
        ->assertSee('<meta name="description" content="Meta description text">', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('projects.show', $project->slug).'">', escape: false)
        ->assertDontSee('noindex');
});

test('non-public projects are a 404 for visitors and non-owner users', function (Closure $makeProject) {
    $project = $makeProject();

    $this->get(route('projects.show', $project->slug))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('projects.show', $project->slug))->assertNotFound();
})->with([
    'draft' => [fn () => Project::factory()->create()],
    'scheduled' => [fn () => Project::factory()->published()->create(['published_at' => now()->addDay()])],
    'trashed' => [fn () => tap(Project::factory()->published()->create())->delete()],
]);

test('the owner sees drafts as a noindex preview', function () {
    $project = Project::factory()->create(['title' => '[DEMO] Secret draft']);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('projects.show', $project->slug))
        ->assertOk()
        ->assertSee('[DEMO] Secret draft')
        ->assertSee('This project is not published')
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
});

test('a signed preview link shows a draft to anyone until it expires', function () {
    $project = Project::factory()->create(['title' => '[DEMO] Shared draft']);
    $url = URL::temporarySignedRoute('projects.preview', now()->addDays(7), ['project' => $project->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('[DEMO] Shared draft')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(route('projects.preview', $project))->assertForbidden();
    $this->get($url.'x')->assertForbidden();

    $this->travel(8)->days();
    $this->get($url)->assertForbidden();
});

test('a signed preview cannot be reused for another project', function () {
    $project = Project::factory()->create();
    $other = Project::factory()->create();
    $url = URL::temporarySignedRoute('projects.preview', now()->addDay(), ['project' => $project->id]);

    $this->get(str_replace("/preview/projects/{$project->id}", "/preview/projects/{$other->id}", $url))->assertForbidden();
});

test('old slugs redirect permanently to the current slug', function () {
    $project = Project::factory()->published()->create(['slug' => 'new-name']);
    $project->slugRedirects()->create(['slug' => 'old-name']);

    $this->get('/projects/old-name')->assertRedirect(route('projects.show', 'new-name'))->assertStatus(301);
});

test('old slugs of unpublished projects do not reveal them', function () {
    $project = Project::factory()->create(['slug' => 'draft-new']);
    $project->slugRedirects()->create(['slug' => 'draft-old']);

    $this->get('/projects/draft-old')->assertNotFound();
});

test('unknown and malformed slugs are a 404', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/projects/does-not-exist', '/projects/Upper-Case', '/projects/..%2F..%2Fetc']);

test('only public gallery images and the public cover are rendered', function () {
    $project = Project::factory()->published()->create();
    $publicImage = MediaAsset::factory()->public()->create();
    $privateImage = MediaAsset::factory()->create();
    $privateCover = MediaAsset::factory()->create();
    $project->forceFill(['cover_media_id' => $privateCover->id])->save();
    ProjectMedia::forceCreate(['project_id' => $project->id, 'media_id' => $publicImage->id, 'caption' => 'Public caption']);
    ProjectMedia::forceCreate(['project_id' => $project->id, 'media_id' => $privateImage->id]);

    $this->get(route('projects.show', $project->slug))
        ->assertSee($publicImage->url('large'))
        ->assertSee('Public caption')
        ->assertDontSee($privateImage->uuid)
        ->assertDontSee($privateCover->uuid);
});

test('videos are embedded from the validated id only', function () {
    $project = Project::factory()->published()->create();
    ProjectVideo::forceCreate(['project_id' => $project->id, 'provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'title' => 'Walkthrough']);

    $this->get(route('projects.show', $project->slug))
        ->assertSee('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', escape: false)
        ->assertSee('title="Walkthrough"', escape: false);
});

test('the project list shows published projects and supports filters', function () {
    $web = Category::factory()->create(['slug' => 'web']);
    $laravel = Skill::factory()->create(['slug' => 'laravel']);
    $inWeb = Project::factory()->published()->create(['title' => '[DEMO] Web one', 'category_id' => $web->id]);
    $inWeb->skills()->attach($laravel);
    Project::factory()->published()->create(['title' => '[DEMO] Mobile one']);
    Project::factory()->create(['title' => '[DEMO] Draft one', 'category_id' => $web->id]);

    $this->get(route('projects.index'))
        ->assertSee('[DEMO] Web one')->assertSee('[DEMO] Mobile one')->assertDontSee('[DEMO] Draft one');

    $this->get(route('projects.index', ['category' => 'web']))
        ->assertSee('[DEMO] Web one')->assertDontSee('[DEMO] Mobile one')
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);

    $this->get(route('projects.index', ['skill' => 'laravel']))
        ->assertSee('[DEMO] Web one')->assertDontSee('[DEMO] Mobile one');

    $this->get(route('projects.index', ['q' => 'Mobile']))
        ->assertSee('[DEMO] Mobile one')->assertDontSee('[DEMO] Web one');

    $this->get(route('projects.index', ['q' => '%']))->assertSee('No projects match these filters.');
});

test('unknown or inactive filters are a 404', function (array $query) {
    Category::factory()->create(['slug' => 'archived', 'is_active' => false]);
    Skill::factory()->create(['slug' => 'hidden-skill', 'is_published' => false]);

    $this->get(route('projects.index', $query))->assertNotFound();
})->with([
    'unknown category' => [['category' => 'nope']],
    'inactive category' => [['category' => 'archived']],
    'unknown skill' => [['skill' => 'nope']],
    'hidden skill' => [['skill' => 'hidden-skill']],
]);

test('filter options only list categories and skills used by published projects', function () {
    $usedCategory = Category::factory()->create(['name' => 'UsedCategory']);
    Category::factory()->create(['name' => 'EmptyCategory']);
    $draftOnly = Category::factory()->create(['name' => 'DraftOnlyCategory']);
    Project::factory()->published()->create(['category_id' => $usedCategory->id]);
    Project::factory()->create(['category_id' => $draftOnly->id]);

    $this->get(route('projects.index'))
        ->assertSee('UsedCategory')
        ->assertDontSee('EmptyCategory')
        ->assertDontSee('DraftOnlyCategory');
});
