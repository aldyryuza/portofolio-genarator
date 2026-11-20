<?php

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\Profile;
use App\Models\Resume;
use App\Models\User;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    Profile::factory()->create(['user_id' => $this->owner->id, 'display_name' => 'Jane Doe']);
});

function storedPdf(User $owner): MediaAsset
{
    $file = UploadedFile::fake()->createWithContent('my cv.pdf', "%PDF-1.4\n%demo cv\n")->mimeType('application/pdf');

    return app(MediaUploader::class)->store($file, [], MediaVisibility::Private, $owner);
}

test('the CV page explains when no CV is published', function () {
    Resume::factory()->create(['file_media_id' => storedPdf($this->owner)->id]);

    $this->get(route('cv'))->assertOk()->assertSee('A downloadable CV is not available yet.');
});

test('the CV page offers the default published version first', function () {
    Resume::factory()->published()->create(['title' => 'Older CV', 'version' => '2025-01', 'file_media_id' => storedPdf($this->owner)->id]);
    $default = Resume::factory()->published()->create(['title' => 'Main CV', 'version' => '2026-11', 'is_default' => true, 'file_media_id' => storedPdf($this->owner)->id]);
    $private = Resume::factory()->create(['title' => 'Private CV', 'file_media_id' => storedPdf($this->owner)->id]);

    $this->get(route('cv'))
        ->assertSeeInOrder(['Main CV', 'Other versions', 'Older CV'])
        ->assertSee(route('cv.download', $default))
        ->assertDontSee('Private CV')
        ->assertDontSee($private->uuid);
});

test('visitors download published CVs as a safe PDF attachment', function () {
    $resume = Resume::factory()->published()->create(['language' => 'en', 'version' => '2026-11', 'file_media_id' => storedPdf($this->owner)->id]);

    $response = $this->get(route('cv.download', $resume))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");

    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename=jane-doe-cv-en-2026-11.pdf')
        ->and($response->streamedContent())->toStartWith('%PDF-');
});

test('unpublished CVs are a 404 for visitors and non-owners but downloadable by the owner', function (array $state) {
    $resume = Resume::factory()->create(['file_media_id' => storedPdf($this->owner)->id, ...$state]);

    $this->get(route('cv.download', $resume))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('cv.download', $resume))->assertNotFound();
    $this->actingAs($this->owner)->get(route('cv.download', $resume))->assertOk();
})->with([
    'private' => [['is_public' => false]],
    'public without publish date' => [['is_public' => true, 'published_at' => null]],
    'scheduled' => [['is_public' => true, 'published_at' => now()->addDay()]],
]);

test('a CV pointing at a non-PDF or missing file is a 404', function () {
    $image = MediaAsset::factory()->public()->create();
    $imageResume = Resume::factory()->published()->create(['file_media_id' => $image->id]);
    $missingResume = Resume::factory()->published()->create(); // factory media row has no file on disk

    $this->get(route('cv.download', $imageResume))->assertNotFound();
    $this->get(route('cv.download', $missingResume))->assertNotFound();
});

test('CV downloads use the uuid, not the numeric id', function () {
    $resume = Resume::factory()->published()->create(['file_media_id' => storedPdf($this->owner)->id]);

    $this->get("/cv/{$resume->id}/download")->assertNotFound();
});
