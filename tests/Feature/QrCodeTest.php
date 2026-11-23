<?php

use App\Models\Project;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PdfRenderer;
use App\Services\Export\PortfolioExportService;
use App\Services\QrCodeGenerator;
use chillerlan\QRCode\QRCode;
use Livewire\Livewire;

function decodeQr(string $png): string
{
    return (string) (new QRCode)->readFromBlob($png);
}

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('generated QR codes decode back to the exact URL', function () {
    $url = 'https://example.com/projects/demo?ref=qr';

    expect(decodeQr(app(QrCodeGenerator::class)->png($url)))->toBe($url)
        ->and(app(QrCodeGenerator::class)->svg($url))->toStartWith('<?xml')->toContain('<svg');
});

test('the owner can preview and download QR codes for public pages', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::admin.qr-codes')
        ->assertSee(route('home', ['ref' => 'qr']))
        ->set('target', 'cv')
        ->assertSee(route('cv', ['ref' => 'qr']))
        ->assertSee('data:image/svg+xml;base64,', escape: false);

    $png = $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', ['target' => 'contact', 'format' => 'png', 'track' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-contact.png"');

    expect(decodeQr($png->getContent()))->toBe(route('contact', ['ref' => 'qr']));

    $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', ['target' => 'home', 'format' => 'svg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

test('scan tracking can be switched off', function () {
    $png = $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', ['target' => 'about', 'format' => 'png', 'track' => 0]))
        ->assertOk();

    expect(decodeQr($png->getContent()))->toBe(route('about'));
});

test('project QR codes only exist for published projects', function () {
    $published = Project::factory()->published()->create(['slug' => 'live-one']);
    $draft = Project::factory()->create();

    $png = $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', ['target' => 'project', 'project' => $published->id, 'format' => 'png']))
        ->assertOk();
    expect(decodeQr($png->getContent()))->toBe(route('projects.show', ['slug' => 'live-one', 'ref' => 'qr']));

    $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', ['target' => 'project', 'project' => $draft->id, 'format' => 'png']))
        ->assertNotFound();
});

test('QR codes cannot be made for arbitrary targets or formats', function (array $query) {
    $this->actingAs($this->owner)
        ->get(route('admin.qr-codes.download', $query))
        ->assertSessionHasErrors();
})->with([
    'external url as target' => [['target' => 'https://evil.test', 'format' => 'png']],
    'unknown format' => [['target' => 'home', 'format' => 'gif']],
]);

test('QR codes are owner-only', function () {
    $this->get(route('admin.qr-codes'))->assertRedirect(route('login'));
    $this->get(route('admin.qr-codes.download', ['target' => 'home', 'format' => 'png']))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.qr-codes'))->assertForbidden();
});

test('a CV export can carry a QR code to the site', function () {
    $options = ExportOptions::fromArray(['document' => 'cv', 'format' => 'pdf', 'scope' => 'all', 'max_projects' => 4, 'template' => 'light', 'locale' => 'en', 'include_qr' => true]);
    $document = app(PortfolioExportService::class)->compose($options);

    expect(decodeQr((string) $document->qrPng))->toBe(route('home', ['ref' => 'qr']))
        ->and(app(PdfRenderer::class)->html($document))->toContain($document->qrDataUri());

    $withoutQr = app(PortfolioExportService::class)->compose(ExportOptions::fromArray([...$options->toArray(), 'include_qr' => false]));
    expect($withoutQr->qrPng)->toBeNull();
});
