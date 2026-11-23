<?php

use App\Enums\ExportDocument;
use App\Enums\MediaVisibility;
use App\Models\Experience;
use App\Models\ExportJob;
use App\Models\ExportPreset;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\ProjectVideo;
use App\Models\Skill;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PdfRenderer;
use App\Services\Export\PortfolioExportService;
use App\Services\Export\PptxRenderer;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpPresentation\IOFactory;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    Profile::factory()->create(['user_id' => $this->owner->id, 'display_name' => 'Jane Doe', 'summary' => 'Builds reliable systems.']);
});

function documentOptions(array $overrides = []): ExportOptions
{
    return ExportOptions::fromArray(['format' => 'pdf', 'scope' => 'all', 'max_projects' => 12, 'template' => 'light', 'locale' => 'en', ...$overrides]);
}

function documentHtml(array $overrides = []): string
{
    return app(PdfRenderer::class)->html(app(PortfolioExportService::class)->compose(documentOptions($overrides)));
}

test('a CV includes career sections and at most four projects without images', function () {
    Experience::factory()->published()->create(['title' => 'Lead Engineer', 'achievements_json' => ['Shipped v2']]);
    Skill::factory()->create(['name' => 'Laravel']);
    Project::factory()->count(6)->published()->sequence(fn ($s) => ['title' => "Project {$s->index}", 'sort_order' => $s->index])->create();

    // Section toggles do not apply to a CV.
    $html = documentHtml(['document' => 'cv', 'include_experience' => false, 'include_education' => false]);

    expect($html)->toContain('Curriculum vitae — Jane Doe')
        ->toContain('Lead Engineer')
        ->toContain('Shipped v2')
        ->toContain('Laravel')
        ->toContain('Project 3')
        ->not->toContain('Project 4')
        ->not->toContain('data:image/jpeg');
});

test('a CV renders as a short PDF', function () {
    Experience::factory()->count(3)->published()->create();
    Project::factory()->count(4)->published()->create();

    $pdf = app(PdfRenderer::class)->render(app(PortfolioExportService::class)->compose(documentOptions(['document' => 'cv'])));

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('#/Type\s*/Page(?!s)#', $pdf))->toBeLessThanOrEqual(2);
});

test('a CV cannot be exported as PPTX and a case study needs selected projects', function (array $input, string $field) {
    try {
        documentOptions($input);
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'cv as pptx' => [['document' => 'cv', 'format' => 'pptx'], 'format'],
    'case study for all projects' => [['document' => 'case_study', 'scope' => 'all'], 'scope'],
    'unknown document' => [['document' => 'brochure'], 'document'],
]);

test('a case study PDF tells the full story of the selected project', function () {
    $project = Project::factory()->published()->create([
        'title' => 'Payment Gateway',
        'challenge' => 'Legacy batch settlement',
        'solution' => 'Event-driven ledger',
        'outcome' => 'Settlement in minutes',
    ]);
    ProjectVideo::forceCreate(['project_id' => $project->id, 'provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'title' => 'Demo']);
    Project::factory()->published()->create(['title' => 'Unselected project']);

    $html = documentHtml(['document' => 'case_study', 'scope' => 'selected', 'project_ids' => [$project->id]]);

    expect($html)->toContain('Case study — Payment Gateway')
        ->toContain('Legacy batch settlement')
        ->toContain('Event-driven ledger')
        ->toContain('Settlement in minutes')
        ->toContain('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->not->toContain('Unselected project');
});

test('a case study PPTX has an opening, one slide per section, a gallery and a closing slide', function () {
    $image = app(MediaUploader::class)->store(UploadedFile::fake()->image('g.jpg', 800, 600), ['alt_text' => 'Screen'], MediaVisibility::Public, $this->owner);
    $project = Project::factory()->published()->create(['challenge' => 'C', 'solution' => 'S', 'outcome' => 'O', 'summary' => null]);
    ProjectMedia::forceCreate(['project_id' => $project->id, 'media_id' => $image->id]);

    $options = documentOptions(['document' => 'case_study', 'format' => 'pptx', 'scope' => 'selected', 'project_ids' => [$project->id]]);
    $path = tempnam(sys_get_temp_dir(), 'pptx');
    file_put_contents($path, app(PptxRenderer::class)->render(app(PortfolioExportService::class)->compose($options)));

    // opening + challenge + solution + outcome + gallery + closing
    expect(IOFactory::load($path)->getSlideCount())->toBe(6);
    @unlink($path);
});

test('the owner picks a document type and the form keeps formats valid', function () {
    Project::factory()->published()->create();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->set('format', 'pptx')
        ->set('document', 'cv')
        ->assertSet('format', 'pdf')
        ->assertDontSee('PowerPoint (PPTX)')
        ->set('document', 'case_study')
        ->assertSet('scope', 'selected');

    $component->set('document', 'cv')->call('generate')->assertHasNoErrors();

    $job = ExportJob::sole();
    expect($job->configuration_json['document'])->toBe(ExportDocument::Cv->value);

    $response = $this->actingAs($this->owner)->get(route('admin.exports.download', $job))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('-cv-');
});

test('presets remember the document type', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->set('document', 'cv')
        ->set('preset_name', 'Recruiter CV')
        ->call('savePreset')
        ->assertHasNoErrors()
        ->set('document', 'portfolio');

    $component->call('loadPreset', ExportPreset::sole()->id)->assertSet('document', 'cv');
});
