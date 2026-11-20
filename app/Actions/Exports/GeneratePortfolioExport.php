<?php

namespace App\Actions\Exports;

use App\Enums\ExportFormat;
use App\Enums\ExportState;
use App\Models\ActivityLog;
use App\Models\ExportJob;
use App\Models\User;
use App\Services\Export\ExportOptions;
use App\Services\Export\PdfRenderer;
use App\Services\Export\PortfolioExportService;
use App\Services\Export\PptxRenderer;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePortfolioExport
{
    private const DISK = 'local';

    public function __construct(
        private PortfolioExportService $composer,
        private PdfRenderer $pdf,
        private PptxRenderer $pptx,
    ) {}

    /**
     * Generates synchronously (portfolio-sized exports finish in seconds) and
     * records the outcome. Files live on the private disk; downloads are owner-only.
     */
    public function handle(ExportOptions $options, User $actor, ?int $presetId = null): ExportJob
    {
        $job = ExportJob::create([
            'export_preset_id' => $presetId,
            'format' => $options->format,
            'state' => ExportState::Pending,
            'configuration_json' => $options->toArray(),
            'requested_by' => $actor->id,
        ]);

        $previousLocale = app()->getLocale();

        try {
            app()->setLocale($options->locale);

            $document = $this->composer->compose($options);
            $contents = $options->format === ExportFormat::Pdf
                ? $this->pdf->render($document)
                : $this->pptx->render($document);

            $path = "exports/{$job->uuid}.{$options->format->value}";
            Storage::disk(self::DISK)->put($path, $contents);

            $job->update([
                'state' => ExportState::Completed,
                'output_disk' => self::DISK,
                'output_path' => $path,
                'output_bytes' => strlen($contents),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $job->update([
                'state' => ExportState::Failed,
                'failed_reason' => mb_substr(class_basename($exception).': '.$exception->getMessage(), 0, 500),
            ]);
        } finally {
            app()->setLocale($previousLocale);
        }

        ActivityLog::record($actor, 'export.'.$job->state->value, $job, ['format' => $options->format->value]);

        return $job;
    }
}
