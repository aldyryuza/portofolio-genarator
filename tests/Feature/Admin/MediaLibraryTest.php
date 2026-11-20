<?php

use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

test('owner uploads an image that is private by default', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->set('upload', UploadedFile::fake()->image('desk.jpg', 1200, 800))
        ->set('alt_text', 'A tidy desk')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('upload', null);

    $media = MediaAsset::sole();

    expect($media)
        ->visibility->toBe(MediaVisibility::Private)
        ->alt_text->toBe('A tidy desk');
    Storage::disk('local')->assertExists($media->variantPath('thumb'));
});

test('owner uploads a PDF document', function () {
    $pdf = UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\n%demo\n");

    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->set('upload', $pdf)
        ->call('save')
        ->assertHasNoErrors();

    expect(MediaAsset::sole()->mime_type)->toBe('application/pdf');
});

test('public images require alt text', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->set('upload', UploadedFile::fake()->image('desk.jpg', 1200, 800))
        ->set('visibility', 'public')
        ->call('save')
        ->assertHasErrors(['alt_text' => 'required']);

    expect(MediaAsset::count())->toBe(0);
});

test('unsafe or unsupported files are rejected', function (Closure $file) {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->set('upload', $file())
        ->call('save')
        ->assertHasErrors('upload');

    expect(MediaAsset::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('media'))->toBe([]);
})->with([
    'svg with script' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>')],
    'php disguised as jpg' => [fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "not an image"; ?>')],
    'html disguised as pdf' => [fn () => UploadedFile::fake()->createWithContent('cv.pdf', '<html><script>alert(1)</script></html>')],
    'gif' => [fn () => UploadedFile::fake()->image('anim.gif', 400, 400)],
    'jpg content with exe extension' => [fn () => UploadedFile::fake()->image('photo.exe', 400, 400)],
    'image too small' => [fn () => UploadedFile::fake()->image('tiny.png', 100, 100)],
    'image too wide' => [fn () => UploadedFile::fake()->image('wide.png', 10001, 300)],
]);

test('images over the byte limit are rejected', function () {
    config(['portfolio.media.image.max_kb' => 1]);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->set('upload', UploadedFile::fake()->image('big.jpg', 1200, 1200))
        ->call('save')
        ->assertHasErrors(['upload' => 'max']);
});

test('owner can edit metadata, but a public image needs alt text', function () {
    $media = MediaAsset::factory()->create(['alt_text' => null]);

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->call('edit', $media->id)
        ->set('edit_visibility', 'public')
        ->call('update')
        ->assertHasErrors(['edit_alt_text' => 'required']);

    expect($media->fresh()->visibility)->toBe(MediaVisibility::Private);

    $component->set('edit_alt_text', 'Team photo')
        ->set('edit_credit', 'Photo: owner')
        ->call('update')
        ->assertHasNoErrors();

    expect($media->fresh())
        ->visibility->toBe(MediaVisibility::Public)
        ->alt_text->toBe('Team photo')
        ->credit->toBe('Photo: owner');
    expect(ActivityLog::where('event', 'media.updated')->sole()->properties_json['changed'])->toContain('visibility', 'alt_text');
});

test('referenced media cannot be deleted', function (Closure $reference) {
    $media = MediaAsset::factory()->public()->create();
    $reference($media);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->call('delete', $media->id)
        ->assertHasErrors('delete');

    expect($media->fresh()->trashed())->toBeFalse();
})->with([
    'project cover' => [fn (MediaAsset $media) => Project::factory()->create()->forceFill(['cover_media_id' => $media->id])->save()],
    'trashed project cover' => [fn (MediaAsset $media) => tap(Project::factory()->create()->forceFill(['cover_media_id' => $media->id]), fn ($p) => $p->save())->delete()],
    'gallery item' => [fn (MediaAsset $media) => ProjectMedia::forceCreate(['project_id' => Project::factory()->create()->id, 'media_id' => $media->id])],
    'profile avatar' => [fn (MediaAsset $media) => Profile::factory()->create()->forceFill(['avatar_media_id' => $media->id])->save()],
]);

test('unreferenced media is soft deleted', function () {
    $media = MediaAsset::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->call('delete', $media->id)
        ->assertHasNoErrors();

    expect($media->fresh()->trashed())->toBeTrue();
});

test('non-owners cannot use the media library', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.media')
        ->assertForbidden();
});
