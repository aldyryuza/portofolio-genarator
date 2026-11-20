<?php

use App\Enums\AvailabilityStatus;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\User;

dataset('public pages', ['home', 'about', 'projects.index', 'experience', 'education', 'cv', 'contact']);

test('public pages render for visitors even before any content exists', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('<link rel="canonical"', escape: false)
        ->assertHeaderMissing('X-Robots-Tag');
})->with('public pages');

test('public pages render the owner profile and visible links', function (string $route) {
    $profile = Profile::factory()->create([
        'display_name' => '[DEMO] Jane Doe',
        'headline' => '[DEMO] Product Engineer',
        'public_email' => 'hello@example.com',
        'availability_status' => AvailabilityStatus::OpenToProjects,
    ]);
    SocialLink::factory()->create(['profile_id' => $profile->id, 'label' => 'Visible link']);
    SocialLink::factory()->create(['profile_id' => $profile->id, 'label' => 'Hidden link', 'is_visible' => false]);

    $this->get(route($route))
        ->assertOk()
        ->assertSee('[DEMO] Jane Doe')
        ->assertSee('Visible link')
        ->assertDontSee('Hidden link')
        ->assertSee('href="'.route('contact').'"', escape: false);
})->with('public pages');

test('a profile of a non-owner user is never used for the public site', function () {
    Profile::factory()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Not The Owner']);

    $this->get(route('home'))->assertOk()->assertDontSee('Not The Owner');
});

test('home shows featured published projects only', function () {
    Project::factory()->published()->featured()->create(['title' => '[DEMO] Live featured']);
    Project::factory()->featured()->create(['title' => '[DEMO] Draft featured']);
    Project::factory()->published()->featured()->create(['title' => '[DEMO] Scheduled', 'published_at' => now()->addWeek()]);
    tap(Project::factory()->published()->featured()->create(['title' => '[DEMO] Trashed']))->delete();

    $this->get(route('home'))
        ->assertSee('[DEMO] Live featured')
        ->assertDontSee('[DEMO] Draft featured')
        ->assertDontSee('[DEMO] Scheduled')
        ->assertDontSee('[DEMO] Trashed');
});

test('home falls back to published projects when none are featured', function () {
    Project::factory()->published()->create(['title' => '[DEMO] Not featured but live']);

    $this->get(route('home'))->assertSee('[DEMO] Not featured but live');
});

test('unpublished career entries and hidden skills are not shown', function () {
    Experience::factory()->published()->create(['company' => '[DEMO] Shown Co']);
    Experience::factory()->create(['company' => '[DEMO] Hidden Co']);
    Education::factory()->published()->create(['institution' => '[DEMO] Shown Uni']);
    Education::factory()->create(['institution' => '[DEMO] Hidden Uni']);
    Certification::factory()->published()->create(['name' => '[DEMO] Shown Cert']);
    Certification::factory()->create(['name' => '[DEMO] Hidden Cert']);
    Skill::factory()->create(['name' => 'ShownSkill']);
    Skill::factory()->create(['name' => 'HiddenSkill', 'is_published' => false]);

    $this->get(route('experience'))->assertSee('[DEMO] Shown Co')->assertDontSee('[DEMO] Hidden Co');
    $this->get(route('education'))
        ->assertSee('[DEMO] Shown Uni')->assertDontSee('[DEMO] Hidden Uni')
        ->assertSee('[DEMO] Shown Cert')->assertDontSee('[DEMO] Hidden Cert');
    $this->get(route('about'))->assertSee('ShownSkill')->assertDontSee('HiddenSkill');
});

test('current roles are labelled present and listed first', function () {
    Experience::factory()->published()->create(['title' => 'Older role', 'start_date' => '2015-01-01', 'end_date' => '2018-01-01']);
    Experience::factory()->published()->current()->create(['title' => 'Current role', 'start_date' => '2021-01-01']);

    $this->get(route('experience'))
        ->assertSeeInOrder(['Present', 'Current role', 'Older role']);
});

test('certificate files are only shown when they are public images', function () {
    $private = MediaAsset::factory()->create();
    $pdf = MediaAsset::factory()->pdf()->public()->create();
    $publicImage = MediaAsset::factory()->public()->create();

    Certification::factory()->published()->create(['file_media_id' => $private->id]);
    Certification::factory()->published()->create(['file_media_id' => $pdf->id]);
    Certification::factory()->published()->create(['file_media_id' => $publicImage->id]);

    $this->get(route('education'))
        ->assertDontSee($private->uuid)
        ->assertDontSee($pdf->uuid)
        ->assertSee($publicImage->url('thumb'));
});

test('owner-entered text is escaped on public pages', function () {
    Profile::factory()->create(['biography' => '<script>alert("bio")</script>', 'headline' => '<img src=x onerror=alert(1)>']);
    Experience::factory()->published()->create(['achievements_json' => ['<b onmouseover=alert(1)>x</b>']]);

    $this->get(route('about'))
        ->assertDontSee('<script>alert("bio")</script>', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false)
        ->assertSee('&lt;script&gt;', escape: false);

    $this->get(route('experience'))->assertDontSee('<b onmouseover=alert(1)>', escape: false);
});

test('structured data cannot break out of its script tag', function () {
    Profile::factory()->create(['headline' => '</script><script>alert(1)</script>']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('</script><script>alert(1)</script>', escape: false);
});

test('custom error pages are used', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found');
});
