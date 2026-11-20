<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneStorage extends Command
{
    protected $signature = 'portfolio:prune
                            {--media-days=30 : Remove files of media deleted more than this many days ago}
                            {--export-days=30 : Remove export files older than this many days}
                            {--dry-run : Only report what would be removed}';

    protected $description = 'Permanently remove files of deleted media and old exports';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mediaDays = max(1, (int) $this->option('media-days'));
        $exportDays = max(1, (int) $this->option('export-days'));

        $media = $this->pruneMedia($mediaDays, $dryRun);
        $exports = $this->pruneExports($exportDays, $dryRun);

        $this->info(sprintf('%s %d media asset(s) and %d export(s).', $dryRun ? 'Would remove' : 'Removed', $media, $exports));

        return self::SUCCESS;
    }

    /**
     * Soft-deleted media is only purged once nothing references it any more.
     */
    private function pruneMedia(int $days, bool $dryRun): int
    {
        $count = 0;

        MediaAsset::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->each(function (MediaAsset $media) use ($dryRun, &$count): void {
                if ($media->isReferenced()) {
                    return;
                }

                $count++;

                if (! $dryRun) {
                    Storage::disk($media->disk)->deleteDirectory($media->path);
                    $media->forceDelete();
                }
            });

        return $count;
    }

    private function pruneExports(int $days, bool $dryRun): int
    {
        $count = 0;

        ExportJob::query()
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (ExportJob $job) use ($dryRun, &$count): void {
                $count++;

                if (! $dryRun) {
                    if ($job->output_disk && $job->output_path) {
                        Storage::disk($job->output_disk)->delete($job->output_path);
                    }

                    $job->delete();
                }
            });

        return $count;
    }
}
