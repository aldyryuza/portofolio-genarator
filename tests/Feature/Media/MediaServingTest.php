<?php

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

function storeImage(User $owner, MediaVisibility $visibility): MediaAsset
{
    return app(MediaUploader::class)->store(UploadedFile::fake()->image('photo.jpg', 800, 600), ['alt_text' => 'Photo'], $visibility, $owner);
}

function storePdf(User $owner, MediaVisibility $visibility): MediaAsset
{
    $file = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%demo\n")->mimeType('application/pdf');

    return app(MediaUploader::class)->store($file, [], $visibility, $owner);
}

test('visitors can load derivatives of public images with safe headers', function (string $variant) {
    $media = storeImage($this->owner, MediaVisibility::Public);

    $this->get($media->url($variant))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox")
        ->assertHeader('Cache-Control', 'max-age=3600, public');
})->with(MediaAsset::IMAGE_VARIANTS);

test('visitors get 404 for private images', function (string $variant) {
    $media = storeImage($this->owner, MediaVisibility::Private);

    $this->get($media->url($variant))->assertNotFound();
})->with(MediaAsset::IMAGE_VARIANTS);

test('non-owner users get 404 for private images', function () {
    $media = storeImage($this->owner, MediaVisibility::Private);

    $this->actingAs(User::factory()->create())->get($media->url('thumb'))->assertNotFound();
});

test('originals are never served to visitors, even for public assets', function () {
    $media = storeImage($this->owner, MediaVisibility::Public);

    $this->get($media->url('original'))->assertNotFound();
    $this->actingAs(User::factory()->create())->get($media->url('original'))->assertNotFound();
});

test('owner can load private derivatives and originals without caching', function () {
    $media = storeImage($this->owner, MediaVisibility::Private);

    $this->actingAs($this->owner)->get($media->url('thumb'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs($this->owner)->get($media->url('original'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('PDF documents download as attachments for the owner only', function () {
    $media = storePdf($this->owner, MediaVisibility::Public);

    $this->get($media->url('original'))->assertNotFound();
    $this->get($media->url('thumb'))->assertNotFound();

    $response = $this->actingAs($this->owner)->get($media->url('original'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('deleted media is not served to anyone', function () {
    $media = storeImage($this->owner, MediaVisibility::Public);
    $media->delete();

    $this->actingAs($this->owner)->get($media->url('thumb'))->assertNotFound();
});

test('unknown variants and malformed ids are rejected', function () {
    $media = storeImage($this->owner, MediaVisibility::Public);

    $this->get("/media/{$media->uuid}/original.jpg")->assertNotFound();
    $this->get("/media/{$media->uuid}/../original")->assertNotFound();
    $this->get("/media/{$media->id}/thumb")->assertNotFound();
});

test('stored files cannot be fetched directly from the storage route', function () {
    $media = storeImage($this->owner, MediaVisibility::Public);

    $status = $this->get('/storage/'.$media->originalPath())->status();

    expect($status)->toBeIn([403, 404]);
});
