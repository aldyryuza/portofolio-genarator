<?php

use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\InvalidMediaException;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->uploader = app(MediaUploader::class);
});

test('stores the original and re-encoded WebP derivatives on the private disk', function () {
    $media = $this->uploader->store(UploadedFile::fake()->image('Holiday Photo.jpg', 2400, 1600), ['alt_text' => 'A desk'], MediaVisibility::Private, $this->owner);

    expect($media)
        ->mime_type->toBe('image/jpeg')
        ->extension->toBe('jpg')
        ->width->toBe(2400)
        ->height->toBe(1600)
        ->original_filename->toBe('Holiday Photo.jpg')
        ->visibility->toBe(MediaVisibility::Private)
        ->uploaded_by->toBe($this->owner->id)
        ->and($media->path)->toBe('media/'.$media->uuid);

    Storage::disk('local')->assertExists([$media->originalPath(), $media->variantPath('large'), $media->variantPath('thumb')]);

    $large = getimagesizefromstring(Storage::disk('local')->get($media->variantPath('large')));
    $thumb = getimagesizefromstring(Storage::disk('local')->get($media->variantPath('thumb')));

    expect($large['mime'])->toBe('image/webp')
        ->and($large[0])->toBe(1920)
        ->and($thumb[0])->toBe(480)
        ->and(ActivityLog::where('event', 'media.uploaded')->exists())->toBeTrue();
});

test('small images are not upscaled', function () {
    $media = $this->uploader->store(UploadedFile::fake()->image('small.png', 300, 300), [], MediaVisibility::Private, $this->owner);

    expect(getimagesizefromstring(Storage::disk('local')->get($media->variantPath('large')))[0])->toBe(300);
});

test('data appended to an image never reaches the served derivatives', function () {
    $file = UploadedFile::fake()->image('polyglot.jpg', 600, 400);
    file_put_contents($file->getRealPath(), '<?php echo "payload"; ?>', FILE_APPEND);

    $media = $this->uploader->store($file, [], MediaVisibility::Public, $this->owner);

    foreach (MediaAsset::IMAGE_VARIANTS as $variant) {
        expect(Storage::disk('local')->get($media->variantPath($variant)))->not->toContain('<?php');
    }
});

test('the stored path ignores the client filename', function () {
    $media = $this->uploader->store(UploadedFile::fake()->image('../../evil.php.jpg', 400, 400), [], MediaVisibility::Private, $this->owner);

    expect($media->originalPath())->toBe("media/{$media->uuid}/original.jpg")
        ->and($media->original_filename)->toBe('evil.php.jpg');
});

test('images over the pixel limit are rejected before decoding', function () {
    config(['portfolio.media.image.max_pixels' => 10_000]);

    $this->uploader->store(UploadedFile::fake()->image('big.jpg', 200, 200), [], MediaVisibility::Private, $this->owner);
})->throws(InvalidMediaException::class);

test('content that is not a supported image is rejected and nothing is stored', function () {
    $file = UploadedFile::fake()->createWithContent('fake.jpg', 'definitely not an image');

    try {
        $this->uploader->store($file, [], MediaVisibility::Private, $this->owner);
        $this->fail('Expected InvalidMediaException');
    } catch (InvalidMediaException) {
        expect(MediaAsset::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    }
});

test('a PDF must start with the PDF signature', function () {
    $file = UploadedFile::fake()->createWithContent('cv.pdf', '<html>not a pdf</html>')->mimeType('application/pdf');

    $this->uploader->store($file, [], MediaVisibility::Private, $this->owner);
})->throws(InvalidMediaException::class);

test('a valid PDF is stored without derivatives', function () {
    $file = UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\n%demo\n")->mimeType('application/pdf');

    $media = $this->uploader->store($file, [], MediaVisibility::Private, $this->owner);

    expect($media)->mime_type->toBe('application/pdf')->width->toBeNull();
    Storage::disk('local')->assertExists($media->originalPath());
    Storage::disk('local')->assertMissing($media->variantPath('thumb'));
});
