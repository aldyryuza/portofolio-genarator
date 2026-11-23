<?php

namespace App\Services\Export;

use App\Enums\ExportDocument;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfRenderer
{
    public function html(PortfolioDocument $document): string
    {
        $view = match ($document->options->document) {
            ExportDocument::Portfolio => 'exports.portfolio',
            ExportDocument::Cv => 'exports.cv',
            ExportDocument::CaseStudy => 'exports.case-study',
        };

        return view($view, [
            'doc' => $document,
            'theme' => self::theme($document->options->template),
        ])->render();
    }

    /**
     * Images arrive as data URIs, so dompdf never needs to read files or the network.
     */
    public function render(PortfolioDocument $document): string
    {
        return Pdf::loadHTML($this->html($document))
            ->setPaper('a4', 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 96,
            ])
            ->output();
    }

    /**
     * @return array{background: string, surface: string, ink: string, muted: string, line: string, accent: string}
     */
    public static function theme(string $template): array
    {
        return match ($template) {
            'dark' => ['background' => '#15171c', 'surface' => '#1e2128', 'ink' => '#f2f2ef', 'muted' => '#a3a7b0', 'line' => '#343842', 'accent' => '#8fb0ff'],
            default => ['background' => '#ffffff', 'surface' => '#f7f6f2', 'ink' => '#1f2430', 'muted' => '#5f6573', 'line' => '#e3e3e0', 'accent' => '#2f4f9e'],
        };
    }
}
