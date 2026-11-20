<?php

use App\Enums\MediaVisibility;
use App\Models\ActivityLog;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\ProjectMedia;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PdfRenderer;
use App\Services\Export\PortfolioExportService;
use App\Services\Export\PptxRenderer;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->project = Project::factory()->published()->create([
        'slug' => 'secret-project',
        'client_label' => 'Acme Bank Indonesia',
        'hide_client' => true,
        'confidential_notes' => 'Runs on cluster prod-k8s-07 behind VPN 10.20.0.0/16',
    ]);
});

function visibilityImage(User $owner, string $alt): MediaAsset
{
    return app(MediaUploader::class)->store(UploadedFile::fake()->image('i.jpg', 800, 600), ['alt_text' => $alt], MediaVisibility::Public, $owner);
}

function exportHtml(array $overrides = []): string
{
    $options = ExportOptions::fromArray(['format' => 'pdf', 'scope' => 'all', 'max_projects' => 5, 'template' => 'light', 'locale' => 'en', ...$overrides]);

    return app(PdfRenderer::class)->html(app(PortfolioExportService::class)->compose($options));
}

test('a hidden client name never reaches visitors', function () {
    $this->get(route('projects.show', 'secret-project'))
        ->assertOk()
        ->assertSee('Confidential client')
        ->assertDontSee('Acme Bank Indonesia');
});

test('confidential notes are never rendered on the public site, not even in the owner preview', function () {
    $this->get(route('projects.show', 'secret-project'))->assertDontSee('prod-k8s-07');

    $this->project->unpublish();
    $this->project->save();

    $this->actingAs($this->owner)
        ->get(route('projects.show', 'secret-project'))
        ->assertOk()
        ->assertDontSee('prod-k8s-07')
        ->assertDontSee('Acme Bank Indonesia');
});

test('confidential gallery images are hidden publicly', function () {
    $shared = visibilityImage($this->owner, 'Shared screenshot');
    $diagram = visibilityImage($this->owner, 'Internal network diagram');
    ProjectMedia::forceCreate(['project_id' => $this->project->id, 'media_id' => $shared->id]);
    ProjectMedia::forceCreate(['project_id' => $this->project->id, 'media_id' => $diagram->id, 'is_confidential' => true]);

    $this->get(route('projects.show', 'secret-project'))
        ->assertSee($shared->uuid)
        ->assertDontSee($diagram->uuid)
        ->assertDontSee('Internal network diagram');
});

test('the owner can set visibility fields and the audit log never stores their content', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $this->project])
        ->assertSet('hide_client', true)
        ->set('confidential_notes', 'New secret: database at db-internal.acme')
        ->set('hide_client', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->project->fresh())
        ->hide_client->toBeFalse()
        ->confidential_notes->toBe('New secret: database at db-internal.acme');

    $log = ActivityLog::where('event', 'project.updated')->sole();
    expect($log->properties_json['changed'])->toContain('confidential_notes', 'hide_client')
        ->and(json_encode($log->properties_json))->not->toContain('db-internal');
});

test('confidential notes are length-limited', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.form', ['project' => $this->project])
        ->set('confidential_notes', str_repeat('a', 10001))
        ->call('save')
        ->assertHasErrors('confidential_notes');
});

test('marking the cover image confidential removes it as cover, and confidential images cannot become cover', function () {
    $image = visibilityImage($this->owner, 'Diagram');
    $item = ProjectMedia::forceCreate(['project_id' => $this->project->id, 'media_id' => $image->id]);
    $this->project->forceFill(['cover_media_id' => $image->id])->save();

    $component = Livewire::actingAs($this->owner)
        ->test('pages::admin.projects.media', ['project' => $this->project])
        ->call('toggleConfidential', $item->id);

    expect($item->fresh()->is_confidential)->toBeTrue()
        ->and($this->project->fresh()->cover_media_id)->toBeNull();

    $component->call('setCover', $image->id)->assertHasErrors('media');
    expect($this->project->fresh()->cover_media_id)->toBeNull();

    $component->call('toggleConfidential', $item->id);
    expect($item->fresh()->is_confidential)->toBeFalse();
});

test('exports hide confidential details by default', function () {
    $diagram = visibilityImage($this->owner, 'Internal network diagram');
    ProjectMedia::forceCreate(['project_id' => $this->project->id, 'media_id' => $diagram->id, 'is_confidential' => true]);

    $html = exportHtml();

    expect($html)->toContain('Confidential client')
        ->not->toContain('Acme Bank Indonesia')
        ->not->toContain('prod-k8s-07')
        ->not->toContain('Internal network diagram');
});

test('an internal export can include confidential details when asked', function () {
    $diagram = visibilityImage($this->owner, 'Internal network diagram');
    ProjectMedia::forceCreate(['project_id' => $this->project->id, 'media_id' => $diagram->id, 'is_confidential' => true]);

    $html = exportHtml(['include_confidential' => true]);

    expect($html)->toContain('Acme Bank Indonesia')
        ->toContain('prod-k8s-07')
        ->toContain('Internal network diagram');
});

test('the PPTX export also hides the client name', function () {
    $options = ExportOptions::fromArray(['format' => 'pptx', 'scope' => 'all', 'max_projects' => 5, 'template' => 'light', 'locale' => 'en']);
    $path = tempnam(sys_get_temp_dir(), 'pptx');
    file_put_contents($path, app(PptxRenderer::class)->render(app(PortfolioExportService::class)->compose($options)));

    $zip = new ZipArchive;
    $zip->open($path);
    $xml = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $xml .= $zip->getFromIndex($i);
    }
    $zip->close();
    @unlink($path);

    expect($xml)->toContain('Confidential client')->not->toContain('Acme Bank Indonesia');
});
