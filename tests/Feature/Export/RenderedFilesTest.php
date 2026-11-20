<?php

use App\Enums\MediaVisibility;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PdfRenderer;
use App\Services\Export\PortfolioExportService;
use App\Services\Export\PptxRenderer;
use App\Services\Media\MediaUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\IOFactory;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    Profile::factory()->create([
        'user_id' => $this->owner->id,
        'display_name' => 'Zoë Ångström-Łukasz',
        'headline' => 'Інженер & <Engineer>',
        'summary' => 'Builds “reliable” systems — café-grade.',
    ]);
});

function renderDocument(string $format, array $overrides = []): string
{
    $options = ExportOptions::fromArray(['format' => $format, 'scope' => 'all', 'max_projects' => 12, 'template' => 'light', 'locale' => 'en', ...$overrides]);
    $document = app(PortfolioExportService::class)->compose($options);
    $output = $format === 'pdf' ? app(PdfRenderer::class)->render($document) : app(PptxRenderer::class)->render($document);

    // Set EXPORT_DUMP_DIR to inspect the files with PowerPoint, LibreOffice or pdftotext.
    if ($dir = getenv('EXPORT_DUMP_DIR')) {
        file_put_contents(rtrim($dir, '/\\').'/'.test()->name().'.'.$format, $output);
    }

    return $output;
}

function pdfPageCount(string $pdf): int
{
    return preg_match_all('#/Type\s*/Page(?!s)#', $pdf);
}

function seedPortfolio(User $owner, int $projects = 2): void
{
    $cover = app(MediaUploader::class)->store(UploadedFile::fake()->image('cover.jpg', 3000, 2000), ['alt_text' => 'Cover'], MediaVisibility::Public, $owner);

    Project::factory()->count($projects)->published()->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create([
        'summary' => str_repeat("A long paragraph about the project.\n\n", 30),
        'outcome' => 'Cut processing time.',
        'cover_media_id' => $cover->id,
    ]);
    Experience::factory()->published()->current()->create(['title' => 'Lead Developer']);
    Education::factory()->published()->create();
    Certification::factory()->published()->create();
}

test('the PDF is a complete document with one section per project', function () {
    seedPortfolio($this->owner, projects: 2);

    $pdf = renderDocument('pdf');

    expect($pdf)->toStartWith('%PDF-')
        ->and(rtrim($pdf))->toEndWith('%%EOF')
        // title + 2 projects (long case studies flow onto extra pages) + experience + education
        ->and(pdfPageCount($pdf))->toBeGreaterThanOrEqual(5);
});

test('a PDF with zero projects still renders', function () {
    $pdf = renderDocument('pdf', ['include_experience' => false, 'include_education' => false]);

    expect($pdf)->toStartWith('%PDF-')
        ->and(pdfPageCount($pdf))->toBe(2);
});

test('PDF text is escaped and Unicode-safe', function () {
    Project::factory()->published()->create(['title' => '<script>alert(1)</script> Ünïcødé']);

    $options = ExportOptions::fromArray(['format' => 'pdf', 'scope' => 'all', 'max_projects' => 5, 'template' => 'dark', 'locale' => 'en']);
    $html = app(PdfRenderer::class)->html(app(PortfolioExportService::class)->compose($options));

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;')
        ->toContain('Ünïcødé')
        ->toContain('Інженер &amp; &lt;Engineer&gt;');
});

test('PDF text can be extracted with pdftotext', function () {
    seedPortfolio($this->owner, projects: 1);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, renderDocument('pdf'));

    exec('pdftotext -enc UTF-8 '.escapeshellarg($path).' -', $lines, $exitCode);
    @unlink($path);
    $text = implode("\n", $lines);

    expect($exitCode)->toBe(0)
        ->and($text)->toContain('Zoë Ångström-Łukasz')
        ->toContain('Інженер')
        ->toContain('Lead Developer');
})->skip(fn () => trim((string) shell_exec('pdftotext -v 2>&1')) === '', 'pdftotext is not installed');

test('the Indonesian locale translates export headings', function () {
    seedPortfolio($this->owner, projects: 1);

    $options = ExportOptions::fromArray(['format' => 'pdf', 'scope' => 'all', 'max_projects' => 5, 'template' => 'light', 'locale' => 'id']);
    app()->setLocale('id');
    $html = app(PdfRenderer::class)->html(app(PortfolioExportService::class)->compose($options));
    app()->setLocale('en');

    expect($html)->toContain('Pengalaman')->toContain('Sekarang')->not->toContain('>Experience<');
});

test('the PPTX is a valid 16:9 deck that PhpPresentation can read back', function () {
    seedPortfolio($this->owner, projects: 2);

    $pptx = renderDocument('pptx');
    $path = tempnam(sys_get_temp_dir(), 'pptx');
    file_put_contents($path, $pptx);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $contentTypes = $zip->getFromName('[Content_Types].xml');
    $slides = collect(range(0, $zip->numFiles - 1))
        ->map(fn ($i) => $zip->getNameIndex($i))
        ->filter(fn ($name) => preg_match('#^ppt/slides/slide\d+\.xml$#', $name))
        ->values();
    $media = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->filter(fn ($name) => str_starts_with($name, 'ppt/media/'));
    $allSlidesXml = $slides->map(fn ($name) => $zip->getFromName($name))->implode('');
    $zip->close();

    // title + 2 projects + experience + education + closing
    expect($contentTypes)->toContain('presentationml.presentation.main+xml')
        ->and($slides)->toHaveCount(6)
        ->and($media)->not->toBeEmpty()
        ->and($allSlidesXml)->toContain('Zoë Ångström-Łukasz')
        // PhpPresentation writes text as CDATA, so markup characters appear literally.
        ->toContain('<![CDATA[Інженер & <Engineer>]]>')
        ->toContain('Lead Developer');

    $loaded = IOFactory::load($path);
    @unlink($path);

    expect($loaded->getSlideCount())->toBe(6)
        ->and($loaded->getLayout()->getDocumentLayout())->toBe('screen16x9');
});

test('a PPTX with zero projects still renders', function () {
    $pptx = renderDocument('pptx', ['include_experience' => false, 'include_education' => false]);
    $path = tempnam(sys_get_temp_dir(), 'pptx');
    file_put_contents($path, $pptx);

    expect(IOFactory::load($path)->getSlideCount())->toBe(3); // title + "no projects" + closing
    @unlink($path);
});

test('PPTX slides stay well-formed XML even with CDATA terminators in content', function () {
    Project::factory()->published()->create(['title' => 'Breaks ]]> CDATA & <tags>', 'excerpt' => 'x ]]]]><![CDATA[> y']);

    $path = tempnam(sys_get_temp_dir(), 'pptx');
    file_put_contents($path, renderDocument('pptx'));

    $zip = new ZipArchive;
    $zip->open($path);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);

        if (str_ends_with($name, '.xml')) {
            $dom = new DOMDocument;
            expect(@$dom->loadXML((string) $zip->getFromName($name)))->toBeTrue("{$name} is not well-formed");
        }
    }

    $zip->close();
    expect(IOFactory::load($path)->getSlide(1)->getShapeCollection())->not->toBeEmpty();
    @unlink($path);
});
