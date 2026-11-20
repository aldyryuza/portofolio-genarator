<?php

use App\Enums\ExportFormat;
use App\Enums\ExportState;
use App\Models\ExportJob;
use App\Models\ExportPreset;
use App\Models\Project;
use App\Models\User;
use App\Services\Export\PdfRenderer;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    Project::factory()->published()->create(['title' => '[DEMO] Exported project']);
});

test('owner generates a PDF and downloads it', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee('Export ready');

    $job = ExportJob::sole();
    expect($job)->state->toBe(ExportState::Completed)->format->toBe(ExportFormat::Pdf);
    Storage::disk('local')->assertExists($job->output_path);

    $response = $this->actingAs($this->owner)->get(route('admin.exports.download', $job))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->streamedContent())->toStartWith('%PDF-');
});

test('owner generates a PPTX', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->set('format', 'pptx')
        ->call('generate')
        ->assertHasNoErrors();

    $job = ExportJob::sole();
    expect($job->format)->toBe(ExportFormat::Pptx)
        ->and(Storage::disk('local')->get($job->output_path))->toStartWith("PK\x03\x04");
});

test('export downloads are owner-only', function () {
    Livewire::actingAs($this->owner)->test('pages::admin.exports')->call('generate');
    $job = ExportJob::sole();

    auth()->logout();
    $this->get(route('admin.exports.download', $job))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.exports.download', $job))->assertForbidden();
    $this->actingAs($this->owner)->get('/admin/exports/00000000-0000-4000-8000-000000000000/download')->assertNotFound();
});

test('a failing renderer marks the export as failed and reports it', function () {
    $this->mock(PdfRenderer::class)->shouldReceive('render')->andThrow(new RuntimeException('Renderer exploded'));

    Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->call('generate')
        ->assertHasErrors('format');

    $job = ExportJob::sole();
    expect($job)->state->toBe(ExportState::Failed)
        ->failed_reason->toContain('Renderer exploded')
        ->output_path->toBeNull();

    $this->actingAs($this->owner)->get(route('admin.exports.download', $job))->assertNotFound();
});

test('invalid options are reported on the form', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->set('scope', 'selected')
        ->set('project_ids', [])
        ->call('generate')
        ->assertHasErrors('project_ids');

    expect(ExportJob::count())->toBe(0);
});

test('presets can be saved, loaded and deleted', function () {
    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.exports')
        ->set(['format' => 'pptx', 'template' => 'dark', 'locale' => 'id', 'max_projects' => 5, 'include_gallery' => false])
        ->set('preset_name', 'Client deck')
        ->call('savePreset')
        ->assertHasNoErrors();

    $preset = ExportPreset::sole();
    expect($preset)->format->toBe(ExportFormat::Pptx)->template_key->toBe('dark')
        ->and($preset->configuration_json['locale'])->toBe('id');

    $component->set(['format' => 'pdf', 'template' => 'light', 'include_gallery' => true])
        ->call('loadPreset', $preset->id)
        ->assertSet('format', 'pptx')
        ->assertSet('template', 'dark')
        ->assertSet('max_projects', 5)
        ->assertSet('include_gallery', false)
        ->call('deletePreset', $preset->id);

    expect(ExportPreset::count())->toBe(0);
});

test('deleting an export removes its file', function () {
    $component = Livewire::actingAs($this->owner)->test('pages::admin.exports')->call('generate');
    $job = ExportJob::sole();

    $component->call('deleteJob', $job->id);

    expect(ExportJob::count())->toBe(0);
    Storage::disk('local')->assertMissing($job->output_path);
});

test('exports are rate limited per user', function () {
    $component = Livewire::actingAs($this->owner)->test('pages::admin.exports');

    foreach (range(1, 6) as $attempt) {
        $component->call('generate')->assertHasNoErrors();
    }

    $component->call('generate')->assertHasErrors('format');
    expect(ExportJob::count())->toBe(6);
});

test('non-owners cannot open the export page', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.exports')
        ->assertForbidden();
});
