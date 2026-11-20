<?php

use App\Enums\MediaVisibility;
use App\Models\Category;
use App\Models\Experience;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PortfolioExportService;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function exportOptions(array $overrides = []): ExportOptions
{
    return ExportOptions::fromArray([
        'format' => 'pdf', 'scope' => 'all', 'max_projects' => 12, 'template' => 'light', 'locale' => 'en', ...$overrides,
    ]);
}

function compose(ExportOptions $options)
{
    return app(PortfolioExportService::class)->compose($options);
}

test('exports contain published projects only by default', function () {
    $live = Project::factory()->published()->create();
    Project::factory()->create();
    Project::factory()->published()->create(['published_at' => now()->addDay()]);
    tap(Project::factory()->published()->create())->delete();

    expect(compose(exportOptions())->projects->pluck('id')->all())->toBe([$live->id]);
});

test('the owner can include drafts but never trashed projects', function () {
    $live = Project::factory()->published()->create(['sort_order' => 1]);
    $draft = Project::factory()->create(['sort_order' => 2]);
    tap(Project::factory()->create(['sort_order' => 3]))->delete();

    expect(compose(exportOptions(['include_drafts' => true]))->projects->pluck('id')->all())->toBe([$live->id, $draft->id]);
});

test('selected and category scopes narrow the projects, in site order', function () {
    $web = Category::factory()->create();
    $a = Project::factory()->published()->create(['sort_order' => 2, 'category_id' => $web->id]);
    $b = Project::factory()->published()->create(['sort_order' => 1]);
    $c = Project::factory()->published()->create(['sort_order' => 3, 'category_id' => $web->id]);

    expect(compose(exportOptions(['scope' => 'selected', 'project_ids' => [$c->id, $b->id]]))->projects->pluck('id')->all())->toBe([$b->id, $c->id])
        ->and(compose(exportOptions(['scope' => 'category', 'category_id' => $web->id]))->projects->pluck('id')->all())->toBe([$a->id, $c->id])
        ->and(compose(exportOptions(['max_projects' => 1]))->projects->pluck('id')->all())->toBe([$b->id]);
});

test('only public images reach an export, and galleries are capped', function () {
    $uploader = app(MediaUploader::class);
    $public = fn () => $uploader->store(UploadedFile::fake()->image('p.jpg', 800, 600), ['alt_text' => 'Public'], MediaVisibility::Public, $this->owner);
    $private = $uploader->store(UploadedFile::fake()->image('x.jpg', 800, 600), [], MediaVisibility::Private, $this->owner);

    $withPublicCover = Project::factory()->published()->create(['sort_order' => 1]);
    $withPublicCover->forceFill(['cover_media_id' => $public()->id])->save();
    $withPrivateCover = Project::factory()->published()->create(['sort_order' => 2]);
    $withPrivateCover->forceFill(['cover_media_id' => $private->id])->save();

    ProjectMedia::forceCreate(['project_id' => $withPublicCover->id, 'media_id' => $private->id, 'sort_order' => 0]);
    foreach (range(1, 5) as $order) {
        ProjectMedia::forceCreate(['project_id' => $withPublicCover->id, 'media_id' => $public()->id, 'sort_order' => $order]);
    }

    $document = compose(exportOptions());

    expect($document->cover($withPublicCover))->not->toBeNull()
        ->and($document->cover($withPublicCover)->jpeg)->toStartWith("\xFF\xD8")
        ->and($document->cover($withPrivateCover))->toBeNull()
        ->and($document->gallery($withPublicCover))->toHaveCount(4)
        ->and(compose(exportOptions(['include_gallery' => false]))->gallery($withPublicCover))->toBe([]);
});

test('missing image files are skipped instead of failing the export', function () {
    $media = app(MediaUploader::class)->store(UploadedFile::fake()->image('p.jpg', 800, 600), ['alt_text' => 'x'], MediaVisibility::Public, $this->owner);
    Storage::disk('local')->delete($media->variantPath('large'));
    $project = Project::factory()->published()->create();
    $project->forceFill(['cover_media_id' => $media->id])->save();

    expect(compose(exportOptions())->cover($project))->toBeNull();
});

test('section toggles leave out profile and career data', function () {
    Experience::factory()->published()->create();

    $document = compose(exportOptions(['include_profile' => false, 'include_experience' => false, 'include_education' => false]));

    expect($document->profile)->toBeNull()
        ->and($document->experiences)->toBeEmpty()
        ->and($document->educations)->toBeEmpty()
        ->and($document->certifications)->toBeEmpty();
});

test('export options are validated', function (array $input) {
    exportOptions($input);
})->with([
    'unknown format' => [['format' => 'docx']],
    'selected without projects' => [['scope' => 'selected', 'project_ids' => []]],
    'category without id' => [['scope' => 'category']],
    'too many projects' => [['max_projects' => 31]],
    'unknown template' => [['template' => 'neon']],
    'unknown locale' => [['locale' => 'fr']],
    'unknown project' => [['scope' => 'selected', 'project_ids' => [999]]],
])->throws(ValidationException::class);

test('trashed projects cannot be selected for export', function () {
    $project = tap(Project::factory()->create())->delete();

    exportOptions(['scope' => 'selected', 'project_ids' => [$project->id]]);
})->throws(ValidationException::class);
