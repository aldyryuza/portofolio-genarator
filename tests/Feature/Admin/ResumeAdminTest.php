<?php

use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

test('owner uploads a CV that stays private until published', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->set(['title' => 'CV', 'language' => 'en', 'version' => '2026-11'])
        ->set('upload', UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%demo\n"))
        ->call('save')
        ->assertHasNoErrors();

    $resume = Resume::sole();

    expect($resume)
        ->is_public->toBeFalse()
        ->is_default->toBeFalse()
        ->published_at->toBeNull()
        ->and($resume->file->mime_type)->toBe('application/pdf')
        ->and($resume->file->visibility)->toBe(MediaVisibility::Private);
});

test('owner can reuse a PDF from the library but not an image', function () {
    $pdf = MediaAsset::factory()->pdf()->create();
    $image = MediaAsset::factory()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->set(['title' => 'CV', 'language' => 'id', 'version' => 'v1', 'file_media_id' => $image->id])
        ->call('save')
        ->assertHasErrors('file_media_id');

    $component->set('file_media_id', $pdf->id)->call('save')->assertHasNoErrors();

    expect(Resume::sole()->file_media_id)->toBe($pdf->id);
});

test('CV input is validated', function (array $input, string $field) {
    Resume::factory()->create(['language' => 'en', 'version' => 'taken']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->set(['title' => 'CV', 'language' => 'en', 'version' => '2026-11', 'file_media_id' => MediaAsset::factory()->pdf()->create()->id, ...$input])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'missing file' => [['file_media_id' => null], 'file_media_id'],
    'bad language code' => [['language' => 'English'], 'language'],
    'version with spaces' => [['version' => 'v 1'], 'version'],
    'duplicate version for language' => [['version' => 'taken'], 'version'],
]);

test('the same version may exist in another language', function () {
    Resume::factory()->create(['language' => 'en', 'version' => '2026-11']);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->set(['title' => 'CV', 'language' => 'id', 'version' => '2026-11', 'file_media_id' => MediaAsset::factory()->pdf()->create()->id])
        ->call('save')
        ->assertHasNoErrors();
});

test('there is only ever one default CV and making one default publishes it', function () {
    $first = Resume::factory()->published()->create(['is_default' => true]);
    $second = Resume::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->call('makeDefault', $second->id);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh())->is_default->toBeTrue()->isDownloadable()->toBeTrue();
});

test('unpublishing a CV also removes it as default', function () {
    $resume = Resume::factory()->published()->create(['is_default' => true]);

    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->call('togglePublished', $resume->id);

    expect($resume->fresh())->is_public->toBeFalse()->is_default->toBeFalse();
});

test('a PDF used by a CV cannot be deleted from the library', function () {
    $resume = Resume::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.media')
        ->call('delete', $resume->file_media_id)
        ->assertHasErrors('delete');
});

test('deleting a CV version keeps its PDF in the library', function () {
    $resume = Resume::factory()->create();

    Livewire::actingAs($this->owner)
        ->test('pages::admin.resumes')
        ->call('delete', $resume->id);

    expect(Resume::count())->toBe(0)
        ->and(MediaAsset::find($resume->file_media_id))->not->toBeNull();
});

test('non-owners cannot manage CVs', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.resumes')
        ->assertForbidden();
});
