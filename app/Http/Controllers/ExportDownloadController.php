<?php

namespace App\Http\Controllers;

use App\Models\ExportJob;
use App\Support\SiteOwner;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    /**
     * Route is inside the owner-only admin group; the policy check is defence in depth.
     */
    public function __invoke(ExportJob $exportJob, SiteOwner $owner): StreamedResponse
    {
        abort_unless(Gate::allows('view', $exportJob), 404);
        abort_unless($exportJob->isReady(), 404);

        $disk = Storage::disk((string) $exportJob->output_disk);
        abort_unless($disk->exists((string) $exportJob->output_path), 404);

        $filename = Str::slug($owner->name().' portfolio '.$exportJob->created_at?->format('Y-m-d')).'.'.$exportJob->format->value;

        return $disk->download((string) $exportJob->output_path, $filename, [
            'Content-Type' => $exportJob->format->mimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
