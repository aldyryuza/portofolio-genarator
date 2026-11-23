<?php

namespace App\Services\Export;

use App\Enums\ExportDocument;
use App\Models\Project;
use Illuminate\Support\Str;
use LogicException;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Drawing\Gd;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as BackgroundColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use RuntimeException;

/**
 * 16:9 deck (960 × 540 px). Text is trimmed to fixed lengths so it never
 * overflows its box; the PDF export carries the full case study.
 */
class PptxRenderer
{
    private const WIDTH = 960;

    private const HEIGHT = 540;

    private const MARGIN = 48;

    private const HEADING_FONT = 'Georgia';

    private const BODY_FONT = 'Arial';

    /** @var array{background: string, surface: string, ink: string, muted: string, line: string, accent: string} */
    private array $theme;

    public function render(PortfolioDocument $document): string
    {
        $this->theme = PdfRenderer::theme($document->options->template);

        $presentation = new PhpPresentation;
        $presentation->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);
        $presentation->getDocumentProperties()
            ->setTitle($document->title())
            ->setCreator($document->ownerName);

        match ($document->options->document) {
            ExportDocument::Portfolio => $this->portfolioDeck($presentation, $document),
            ExportDocument::CaseStudy => $this->caseStudyDeck($presentation, $document),
            // ExportOptions rejects this combination; guard against direct calls.
            ExportDocument::Cv => throw new LogicException('A CV can only be exported as PDF.'),
        };

        return $this->write($presentation);
    }

    private function portfolioDeck(PhpPresentation $presentation, PortfolioDocument $document): void
    {
        $this->titleSlide($presentation->getActiveSlide(), $document);

        foreach ($document->projects as $project) {
            $this->projectSlide($presentation->createSlide(), $document, $project);

            if ($document->gallery($project) !== []) {
                $this->gallerySlide($presentation->createSlide(), $document, $project);
            }
        }

        if ($document->projects->isEmpty()) {
            $slide = $this->blankSlide($presentation->createSlide());
            $this->text($slide, __('Projects'), self::MARGIN, 60, 860, 60, 30, heading: true);
            $this->text($slide, __('No projects match the selected export options.'), self::MARGIN, 140, 860, 40, 16, color: 'muted');
        }

        if ($document->experiences->isNotEmpty()) {
            $this->experienceSlide($presentation->createSlide(), $document);
        }

        if ($document->educations->isNotEmpty() || $document->certifications->isNotEmpty()) {
            $this->educationSlide($presentation->createSlide(), $document);
        }

        $this->closingSlide($presentation->createSlide(), $document);
    }

    /**
     * Per project: opening slide, one slide per story section, then the gallery.
     */
    private function caseStudyDeck(PhpPresentation $presentation, PortfolioDocument $document): void
    {
        $first = true;

        foreach ($document->projects as $project) {
            $slide = $first ? $presentation->getActiveSlide() : $presentation->createSlide();
            $first = false;

            $this->projectSlide($slide, $document, $project);

            $sections = array_filter([
                __('Overview') => $project->summary,
                __('The challenge') => $project->challenge,
                __('Approach & responsibilities') => $project->solution,
                __('Outcome') => $project->outcome,
                __('Confidential notes') => $document->options->includeConfidential ? $project->confidential_notes : null,
            ], 'filled');

            foreach ($sections as $heading => $text) {
                $this->sectionSlide($presentation->createSlide(), $project, $heading, (string) $text);
            }

            if ($document->gallery($project) !== []) {
                $this->gallerySlide($presentation->createSlide(), $document, $project);
            }
        }

        if ($document->projects->isEmpty()) {
            $slide = $this->blankSlide($presentation->getActiveSlide());
            $this->text($slide, __('Case study'), self::MARGIN, 60, 860, 60, 30, heading: true);
            $this->text($slide, __('No projects match the selected export options.'), self::MARGIN, 140, 860, 40, 16, color: 'muted');
        }

        $this->closingSlide($presentation->createSlide(), $document);
    }

    private function sectionSlide(Slide $slide, Project $project, string $heading, string $text): void
    {
        $this->blankSlide($slide);
        $this->text($slide, Str::upper(Str::limit($project->title, 70)), self::MARGIN, self::MARGIN, 860, 22, 10, color: 'accent');
        $this->text($slide, $heading, self::MARGIN, self::MARGIN + 28, 860, 50, 28, heading: true);
        // Long sections are trimmed to stay readable; the PDF case study carries the full text.
        $this->text($slide, Str::limit(preg_replace('/\s+/', ' ', $text) ?? $text, 700), self::MARGIN, 150, 860, 340, 16);
    }

    private function titleSlide(Slide $slide, PortfolioDocument $document): void
    {
        $this->blankSlide($slide);
        $profile = $document->profile;
        $textWidth = $document->avatar ? 600 : 860;

        $this->text($slide, Str::upper(__('Portfolio')), self::MARGIN, 150, $textWidth, 24, 11, color: 'muted');
        $this->text($slide, $document->ownerName, self::MARGIN, 176, $textWidth, 80, 40, heading: true);

        if ($profile?->headline) {
            $this->text($slide, Str::limit($profile->headline, 90), self::MARGIN, 262, $textWidth, 34, 18, color: 'muted');
        }

        if ($profile?->summary) {
            $this->text($slide, Str::limit($profile->summary, 260), self::MARGIN, 310, $textWidth, 110, 14);
        }

        if ($document->avatar) {
            $this->image($slide, $document->avatar, 700, 150, 210, 210);
        }
    }

    private function projectSlide(Slide $slide, PortfolioDocument $document, Project $project): void
    {
        $this->blankSlide($slide);
        $cover = $document->cover($project);
        $textWidth = $cover ? 420 : 860;
        $y = self::MARGIN;

        if ($project->category) {
            $this->text($slide, Str::upper($project->category->name), self::MARGIN, $y, $textWidth, 22, 10, color: 'accent');
            $y += 26;
        }

        $this->text($slide, Str::limit($project->title, 70), self::MARGIN, $y, $textWidth, 80, 26, heading: true);
        $y += 86;

        if ($project->excerpt) {
            $this->text($slide, Str::limit($project->excerpt, 200), self::MARGIN, $y, $textWidth, 70, 13, color: 'muted');
            $y += 76;
        }

        $meta = array_filter([
            __('Role') => $project->role,
            __('Client') => $document->client($project),
            __('Technologies') => $project->skills->take(6)->pluck('name')->implode(', '),
        ], 'filled');

        foreach ($meta as $label => $value) {
            $this->text($slide, Str::upper($label).'  '.Str::limit((string) $value, 70), self::MARGIN, $y, $textWidth, 22, 10);
            $y += 24;
        }

        if ($project->outcome) {
            $this->text($slide, Str::limit($project->outcome, 220), self::MARGIN, max($y + 10, 380), $textWidth, 110, 13, color: 'accent');
        }

        if ($cover) {
            $this->image($slide, $cover, 500, self::MARGIN, 412, self::HEIGHT - 2 * self::MARGIN);
        }
    }

    private function gallerySlide(Slide $slide, PortfolioDocument $document, Project $project): void
    {
        $this->blankSlide($slide);
        $this->text($slide, Str::limit($project->title, 70).' — '.__('Gallery'), self::MARGIN, self::MARGIN, 860, 40, 20, heading: true);

        $images = array_slice($document->gallery($project), 0, 3);
        $gap = 16;
        $boxWidth = (int) floor((self::WIDTH - 2 * self::MARGIN - $gap * (count($images) - 1)) / count($images));

        foreach ($images as $index => $image) {
            $this->image($slide, $image, self::MARGIN + $index * ($boxWidth + $gap), 120, $boxWidth, 360);
        }
    }

    private function experienceSlide(Slide $slide, PortfolioDocument $document): void
    {
        $this->blankSlide($slide);
        $this->text($slide, __('Experience'), self::MARGIN, self::MARGIN, 860, 50, 28, heading: true);

        $y = 120;

        foreach ($document->experiences->take(5) as $experience) {
            $end = $experience->is_current ? $this->translate('Present') : (string) $experience->end_date?->translatedFormat('M Y');
            $period = $experience->start_date->translatedFormat('M Y').' – '.$end;

            $this->text($slide, $period, self::MARGIN, $y, 200, 40, 11, color: 'muted');
            $this->text($slide, Str::limit($experience->title, 60), 260, $y, 650, 24, 15, bold: true);
            $this->text($slide, Str::limit($experience->company.' · '.$experience->employment_type->label(), 80), 260, $y + 24, 650, 22, 11, color: 'muted');
            $y += 76;
        }
    }

    private function educationSlide(Slide $slide, PortfolioDocument $document): void
    {
        $this->blankSlide($slide);
        $columnWidth = 410;

        $this->text($slide, __('Education'), self::MARGIN, self::MARGIN, $columnWidth, 50, 24, heading: true);
        $y = 116;

        foreach ($document->educations->take(4) as $education) {
            $this->text($slide, Str::limit($education->institution, 50), self::MARGIN, $y, $columnWidth, 24, 14, bold: true);
            $detail = collect([$education->degree, $education->field_of_study, $education->end_date?->format('Y')])->filter()->implode(' · ');
            $this->text($slide, Str::limit($detail, 70), self::MARGIN, $y + 24, $columnWidth, 22, 11, color: 'muted');
            $y += 70;
        }

        $x = self::MARGIN + $columnWidth + 40;
        $this->text($slide, __('Certifications'), $x, self::MARGIN, $columnWidth, 50, 24, heading: true);
        $y = 116;

        foreach ($document->certifications->take(5) as $certification) {
            $this->text($slide, Str::limit($certification->name, 50), $x, $y, $columnWidth, 24, 14, bold: true);
            $this->text($slide, Str::limit($certification->issuer.' · '.$certification->issue_date->translatedFormat('M Y'), 70), $x, $y + 24, $columnWidth, 22, 11, color: 'muted');
            $y += 62;
        }
    }

    private function closingSlide(Slide $slide, PortfolioDocument $document): void
    {
        $this->blankSlide($slide);
        $this->text($slide, __('Thank you'), self::MARGIN, 180, 860, 70, 40, heading: true);

        $contact = collect([$document->profile?->public_email, route('home')])->filter()->implode('   ·   ');
        $this->text($slide, $contact, self::MARGIN, 270, 860, 30, 16, color: 'muted');

        if ($document->qrPng !== null && ($qr = @imagecreatefromstring($document->qrPng)) instanceof \GdImage) {
            $shape = (new Gd)
                ->setImageResource($qr)
                ->setRenderingFunction(Gd::RENDERING_PNG)
                ->setMimeType(Gd::MIMETYPE_PNG)
                ->setName('QR code')
                ->setDescription($this->xmlSafe(route('home')));
            $shape->setResizeProportional(false)->setWidth(150)->setHeight(150)->setOffsetX(self::MARGIN)->setOffsetY(330);
            $slide->addShape($shape);
        }
    }

    private function blankSlide(Slide $slide): Slide
    {
        $slide->setBackground((new BackgroundColor)->setColor($this->color('background')));

        return $slide;
    }

    private function text(
        Slide $slide,
        string $text,
        int $x,
        int $y,
        int $width,
        int $height,
        int $size,
        bool $heading = false,
        bool $bold = false,
        string $color = 'ink',
    ): void {
        $shape = $slide->createRichTextShape()
            ->setOffsetX($x)
            ->setOffsetY($y)
            ->setWidth($width)
            ->setHeight($height);

        $shape->setWrap(RichText::WRAP_SQUARE)->setAutoFit(RichText::AUTOFIT_NOAUTOFIT);
        $shape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $shape->createTextRun($this->xmlSafe($text))->getFont()
            ->setName($heading ? self::HEADING_FONT : self::BODY_FONT)
            ->setSize($size)
            ->setBold($bold)
            ->setColor($this->color($color));
    }

    private function image(Slide $slide, ExportImage $image, int $x, int $y, int $maxWidth, int $maxHeight): void
    {
        $gd = $image->gd();

        if ($gd === null) {
            return;
        }

        [$width, $height] = $image->fitWithin($maxWidth, $maxHeight);

        $shape = (new Gd)
            ->setImageResource($gd)
            ->setRenderingFunction(Gd::RENDERING_JPEG)
            ->setMimeType(Gd::MIMETYPE_JPEG)
            ->setName($this->xmlSafe(Str::limit($image->alt !== '' ? $image->alt : 'Image', 60, '')))
            ->setDescription($this->xmlSafe($image->alt));
        $shape->setResizeProportional(false)
            ->setWidth($width)
            ->setHeight($height)
            ->setOffsetX($x + intdiv($maxWidth - $width, 2))
            ->setOffsetY($y + intdiv($maxHeight - $height, 2));

        $slide->addShape($shape);
    }

    /**
     * PhpPresentation writes text as CDATA without splitting "]]>", and XML 1.0
     * forbids most control characters; either would produce a corrupt deck.
     */
    private function xmlSafe(string $text): string
    {
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // A zero-width space keeps the text visually identical.
        return str_replace(']]>', "]]\u{200B}>", $text);
    }

    private function translate(string $key): string
    {
        $translated = __($key);

        return is_string($translated) ? $translated : $key;
    }

    private function color(string $key): Color
    {
        return new Color('FF'.strtoupper(ltrim($this->theme[$key], '#')));
    }

    private function write(PhpPresentation $presentation): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pptx');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the PPTX export.');
        }

        try {
            IOFactory::createWriter($presentation, 'PowerPoint2007')->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
