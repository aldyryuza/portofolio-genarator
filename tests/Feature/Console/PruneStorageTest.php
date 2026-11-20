<?php

use App\Enums\MediaVisibility;
use App\Models\ExportJob;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\User;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

function uploadedImage(User $owner): MediaAsset
{
    return app(MediaUploader::class)->store(UploadedFile::fake()->image('a.jpg', 400, 400), [], MediaVisibility::Private, $owner);
}

test('files of media deleted long ago are purged', function () {
    $old = uploadedImage($this->owner);
    $old->delete();
    $this->travel(31)->days();

    $this->artisan('portfolio:prune')->assertSuccessful();

    expect(MediaAsset::withTrashed()->find($old->id))->toBeNull();
    Storage::disk('local')->assertMissing($old->originalPath());
});

test('recently deleted, live and still-referenced media are kept', function () {
    $live = uploadedImage($this->owner);
    $recent = uploadedImage($this->owner);
    $referenced = uploadedImage($this->owner);
    ProjectMedia::forceCreate(['project_id' => Project::factory()->create()->id, 'media_id' => $referenced->id]);

    $this->travel(31)->days();
    $referenced->delete(); // deleted outside the app's guard, still referenced
    $this->travel(31)->days();
    $recent->delete();

    $this->artisan('portfolio:prune')->assertSuccessful();

    expect(MediaAsset::find($live->id))->not->toBeNull()
        ->and(MediaAsset::withTrashed()->find($recent->id))->not->toBeNull()
        ->and(MediaAsset::withTrashed()->find($referenced->id))->not->toBeNull();
    Storage::disk('local')->assertExists([$live->originalPath(), $recent->originalPath(), $referenced->originalPath()]);
});

test('old export files are removed', function () {
    Storage::disk('local')->put('exports/old.pdf', '%PDF-');
    Storage::disk('local')->put('exports/new.pdf', '%PDF-');
    $old = ExportJob::create(['format' => 'pdf', 'state' => 'completed', 'configuration_json' => [], 'output_disk' => 'local', 'output_path' => 'exports/old.pdf']);
    $this->travel(31)->days();
    $new = ExportJob::create(['format' => 'pdf', 'state' => 'completed', 'configuration_json' => [], 'output_disk' => 'local', 'output_path' => 'exports/new.pdf']);

    $this->artisan('portfolio:prune')->assertSuccessful();

    expect(ExportJob::find($old->id))->toBeNull()->and(ExportJob::find($new->id))->not->toBeNull();
    Storage::disk('local')->assertMissing('exports/old.pdf');
    Storage::disk('local')->assertExists('exports/new.pdf');
});

test('dry run removes nothing', function () {
    $media = uploadedImage($this->owner);
    $media->delete();
    $this->travel(31)->days();

    $this->artisan('portfolio:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Would remove 1 media asset(s)')
        ->assertSuccessful();

    expect(MediaAsset::withTrashed()->find($media->id))->not->toBeNull();
});

test('pruning is scheduled daily', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('portfolio:prune')->assertSuccessful();
});
