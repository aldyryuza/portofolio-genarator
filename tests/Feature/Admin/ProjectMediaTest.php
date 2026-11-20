<?php

use App\Enums\MediaVisibility;
use App\Enums\VideoProvider;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->project = Project::factory()->create();
});

function projectMedia(Project $project)
{
    return Livewire::actingAs(test()->owner)->test('pages::admin.projects.media', ['project' => $project]);
}

test('owner can build an ordered gallery from the library', function () {
    [$first, $second] = MediaAsset::factory()->count(2)->create();

    $component = projectMedia($this->project)
        ->call('attach', $first->id)
        ->call('attach', $second->id)
        ->call('attach', $first->id); // duplicate attach is ignored

    expect($this->project->mediaItems()->pluck('media_id')->all())->toBe([$first->id, $second->id]);

    $secondItem = ProjectMedia::where('media_id', $second->id)->sole();
    $component->call('moveItem', $secondItem->id, 'up');

    expect($this->project->mediaItems()->pluck('media_id')->all())->toBe([$second->id, $first->id]);
});

test('owner can caption and remove gallery items without deleting the file', function () {
    $media = MediaAsset::factory()->create();

    $component = projectMedia($this->project)->call('attach', $media->id);
    $item = ProjectMedia::sole();

    $component->set("captions.{$item->id}", 'Login screen')->call('saveCaption', $item->id)->assertHasNoErrors();
    expect($item->fresh()->caption)->toBe('Login screen');

    $component->set("captions.{$item->id}", str_repeat('a', 501))->call('saveCaption', $item->id)->assertHasErrors("captions.{$item->id}");

    $component->call('detach', $item->id);
    expect(ProjectMedia::count())->toBe(0)
        ->and($media->fresh()->trashed())->toBeFalse();
});

test('gallery items of another project cannot be changed', function () {
    $other = ProjectMedia::forceCreate(['project_id' => Project::factory()->create()->id, 'media_id' => MediaAsset::factory()->create()->id]);

    projectMedia($this->project)->call('detach', $other->id)->assertNotFound();

    expect($other->fresh())->not->toBeNull();
});

test('only images can be attached or used as cover', function (string $method) {
    $pdf = MediaAsset::factory()->pdf()->create();

    projectMedia($this->project)->call($method, $pdf->id)->assertNotFound();

    expect(ProjectMedia::count())->toBe(0)
        ->and($this->project->fresh()->cover_media_id)->toBeNull();
})->with(['attach', 'setCover']);

test('owner can set and clear the cover', function () {
    $media = MediaAsset::factory()->create();

    $component = projectMedia($this->project)->call('setCover', $media->id);
    expect($this->project->fresh()->cover_media_id)->toBe($media->id);

    $component->call('clearCover');
    expect($this->project->fresh()->cover_media_id)->toBeNull();
});

test('making an image public from the project requires alt text', function () {
    $withoutAlt = MediaAsset::factory()->create(['alt_text' => null]);
    $withAlt = MediaAsset::factory()->create(['alt_text' => 'Dashboard']);

    projectMedia($this->project)
        ->call('makePublic', $withoutAlt->id)
        ->assertHasErrors('media')
        ->call('makePublic', $withAlt->id);

    expect($withoutAlt->fresh()->visibility)->toBe(MediaVisibility::Private)
        ->and($withAlt->fresh()->visibility)->toBe(MediaVisibility::Public);
});

test('uploading to the gallery stores and attaches the image', function () {
    projectMedia($this->project)
        ->set('upload', UploadedFile::fake()->image('screen.png', 1280, 720))
        ->set('upload_alt_text', 'Screen')
        ->call('uploadToGallery')
        ->assertHasNoErrors();

    expect($this->project->mediaItems()->sole()->media->alt_text)->toBe('Screen');
});

test('the gallery upload rejects PDFs', function () {
    projectMedia($this->project)
        ->set('upload', UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%demo\n"))
        ->call('uploadToGallery')
        ->assertHasErrors('upload');

    expect(MediaAsset::count())->toBe(0);
});

test('owner can add, reorder and remove videos', function () {
    $component = projectMedia($this->project)
        ->set('video_url', 'https://youtu.be/dQw4w9WgXcQ')
        ->set('video_title', 'Demo walkthrough')
        ->call('addVideo')
        ->assertHasNoErrors()
        ->set('video_url', 'https://vimeo.com/76979871')
        ->call('addVideo')
        ->assertHasNoErrors();

    $videos = $this->project->videos()->get();
    expect($videos->pluck('provider')->all())->toBe([VideoProvider::YouTube, VideoProvider::Vimeo])
        ->and($videos->first()->embedUrl())->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');

    $component->call('moveVideo', $videos->last()->id, 'up');
    expect($this->project->videos()->pluck('provider')->first())->toBe(VideoProvider::Vimeo);

    $component->call('removeVideo', $videos->first()->id);
    expect($this->project->videos()->count())->toBe(1);
});

test('invalid and duplicate videos are rejected', function () {
    projectMedia($this->project)
        ->set('video_url', '<iframe src="https://evil.test"></iframe>')
        ->call('addVideo')
        ->assertHasErrors('video_url')
        ->set('video_url', 'https://youtu.be/dQw4w9WgXcQ')
        ->call('addVideo')
        ->set('video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->call('addVideo')
        ->assertHasErrors('video_url');

    expect($this->project->videos()->count())->toBe(1);
});

test('non-owners cannot manage project media', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.projects.media', ['project' => $this->project])
        ->assertForbidden();
});

test('profile avatar must be a public image', function () {
    $private = MediaAsset::factory()->create();
    $public = MediaAsset::factory()->public()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.profile')
        ->set('avatar_media_id', $private->id)
        ->call('save')
        ->assertHasErrors('avatar_media_id')
        ->set('avatar_media_id', $public->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->owner->fresh()->profile->avatar_media_id)->toBe($public->id);
});
