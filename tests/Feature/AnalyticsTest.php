<?php

use App\Enums\AnalyticsEventType;
use App\Enums\MediaVisibility;
use App\Models\AnalyticsEvent;
use App\Models\Project;
use App\Models\Resume;
use App\Models\User;
use App\Services\Analytics\AnalyticsReport;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';
const PHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

function browseAs(string $uri, array $headers = [])
{
    return test()->withHeaders(['User-Agent' => BROWSER_UA, ...$headers])->get($uri);
}

test('public page views are recorded without storing personal data', function () {
    browseAs('/about', ['REMOTE_ADDR' => '203.0.113.7'])->assertOk();

    $event = AnalyticsEvent::sole();

    expect($event)
        ->type->toBe(AnalyticsEventType::PageView)
        ->path->toBe('/about')
        ->device->toBe('desktop')
        ->visitor_hash->toHaveLength(64)
        ->and(json_encode($event->getAttributes()))->not->toContain('203.0.113.7')->not->toContain('Chrome');
});

test('project views are attributed to the project', function () {
    $project = Project::factory()->published()->create(['slug' => 'tracked']);

    browseAs('/projects/tracked')->assertOk();

    expect(AnalyticsEvent::sole()->project_id)->toBe($project->id);
});

test('visits that must not be counted are skipped', function (Closure $request) {
    $request();

    expect(AnalyticsEvent::count())->toBe(0);
})->with([
    'owner browsing the site' => [fn () => test()->actingAs(User::factory()->owner()->create())->withHeaders(['User-Agent' => BROWSER_UA])->get('/')],
    'search engine bot' => [fn () => test()->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/')],
    'Lighthouse / headless chrome' => [fn () => test()->withHeaders(['User-Agent' => 'Mozilla/5.0 HeadlessChrome/131.0 Chrome-Lighthouse'])->get('/')],
    'no user agent' => [fn () => test()->withHeaders(['User-Agent' => ''])->get('/')],
    'do not track' => [fn () => browseAs('/', ['DNT' => '1'])],
    'global privacy control' => [fn () => browseAs('/', ['Sec-GPC' => '1'])],
    'missing page' => [fn () => browseAs('/projects/does-not-exist')],
    'draft project seen by nobody' => [fn () => browseAs('/projects/'.Project::factory()->create()->slug)],
    'signed preview' => [fn () => browseAs(URL::temporarySignedRoute('projects.preview', now()->addDay(), ['project' => Project::factory()->create()->id]))],
    'admin login page' => [fn () => browseAs('/login')],
    'robots.txt' => [fn () => browseAs('/robots.txt')],
    'sitemap.xml' => [fn () => browseAs('/sitemap.xml')],
]);

test('QR visits, referrers and devices are classified', function () {
    browseAs('/?ref=qr', ['User-Agent' => PHONE_UA, 'Referer' => 'https://www.linkedin.com/in/someone?x=private'])->assertOk();
    browseAs('/about?ref=<script>', ['Referer' => 'http://localhost/projects'])->assertOk();

    [$fromQr, $internal] = AnalyticsEvent::orderBy('id')->get()->all();

    expect($fromQr)
        ->source->toBe('qr')
        ->referrer_host->toBe('linkedin.com')
        ->device->toBe('mobile')
        ->and($internal)
        ->source->toBeNull()
        ->referrer_host->toBeNull();
});

test('the visitor hash is stable within a day and rotates daily', function () {
    browseAs('/');
    browseAs('/about');
    $this->travel(1)->day();
    browseAs('/');

    $hashes = AnalyticsEvent::orderBy('id')->pluck('visitor_hash')->all();

    expect($hashes[0])->toBe($hashes[1])->and($hashes[2])->not->toBe($hashes[0]);
});

test('CV downloads are counted, but not the owner\'s own', function () {
    Storage::fake('local');
    $owner = User::factory()->owner()->create();
    $file = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%demo\n")->mimeType('application/pdf');
    $media = app(MediaUploader::class)->store($file, [], MediaVisibility::Private, $owner);
    $resume = Resume::factory()->published()->create(['file_media_id' => $media->id]);

    browseAs(route('cv.download', $resume))->assertOk();
    $this->actingAs($owner)->withHeaders(['User-Agent' => BROWSER_UA])->get(route('cv.download', $resume))->assertOk();

    expect(AnalyticsEvent::where('type', AnalyticsEventType::CvDownload)->sole()->resume_id)->toBe($resume->id);
});

test('the report aggregates totals, daily series and popular projects', function () {
    $popular = Project::factory()->published()->create(['slug' => 'popular', 'title' => 'Popular project']);
    Project::factory()->published()->create(['slug' => 'quiet', 'title' => 'Quiet project']);

    browseAs('/projects/popular', ['REMOTE_ADDR' => '198.51.100.1']);
    browseAs('/projects/popular', ['REMOTE_ADDR' => '198.51.100.2']);
    browseAs('/projects/quiet?ref=qr', ['REMOTE_ADDR' => '198.51.100.1']);

    $report = new AnalyticsReport(7);
    $daily = $report->daily();

    expect($report->totals())->toBe(['views' => 3, 'visitors' => 2, 'cv_downloads' => 0, 'qr_visits' => 1])
        ->and($daily)->toHaveCount(7)
        ->and(end($daily)['views'])->toBe(3)
        ->and($daily[0]['views'])->toBe(0)
        ->and($report->topProjects()->first()['project']->is($popular))->toBeTrue()
        ->and($report->sources()->first())->toBe(['label' => 'qr', 'count' => 1]);
});

test('events outside the range are not counted and an unknown range falls back to 30 days', function () {
    browseAs('/');
    $this->travel(10)->days();
    browseAs('/');

    expect((new AnalyticsReport(7))->totals()['views'])->toBe(1)
        ->and((new AnalyticsReport(30))->totals()['views'])->toBe(2)
        ->and((new AnalyticsReport(12345))->days())->toBe(30);
});

test('the owner sees the analytics page and can switch ranges', function () {
    Project::factory()->published()->create(['slug' => 'seen', 'title' => 'Seen project']);
    browseAs('/projects/seen?ref=qr', ['User-Agent' => PHONE_UA]);

    Livewire::actingAs(User::factory()->owner()->create())
        ->test('pages::admin.analytics')
        ->assertSee('Seen project')
        ->assertSee('Visits from QR codes')
        ->assertSee('/projects/seen')
        ->set('range', 7)
        ->assertSee('Seen project')
        ->assertSee('Show as table');
});

test('the analytics page shows an empty state and is owner-only', function () {
    $this->get(route('admin.analytics'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.analytics'))->assertForbidden();

    Livewire::actingAs(User::factory()->owner()->create())
        ->test('pages::admin.analytics')
        ->assertSee('No visits recorded in this period yet.');
});

test('old analytics events are pruned', function () {
    browseAs('/');
    $this->travel(400)->days();
    browseAs('/');

    $this->artisan('portfolio:prune')->assertSuccessful();

    expect(AnalyticsEvent::count())->toBe(1);
});
