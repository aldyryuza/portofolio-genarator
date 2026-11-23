<?php

namespace App\Http\Controllers\Site;

use App\Enums\AnalyticsEventType;
use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Services\Analytics\AnalyticsRecorder;
use App\Support\SiteOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeController extends Controller
{
    public function index(): View
    {
        $resumes = Resume::query()->downloadable()
            ->orderByDesc('is_default')
            ->orderByDesc('published_at')
            ->get();

        return view('site.cv', [
            'default' => $resumes->firstWhere('is_default', true) ?? $resumes->first(),
            'resumes' => $resumes,
        ]);
    }

    /**
     * Private or unpublished CVs answer 404 so their existence is not revealed.
     */
    public function download(Request $request, Resume $resume, SiteOwner $owner, AnalyticsRecorder $analytics): StreamedResponse
    {
        abort_unless(Gate::allows('download', $resume), 404);

        $file = $resume->file;

        abort_if($file === null || $file->mime_type !== 'application/pdf', 404);

        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->originalPath()), 404);

        $analytics->record(AnalyticsEventType::CvDownload, $request, ['resume_id' => $resume->id]);

        return $disk->download($file->originalPath(), $resume->downloadFilename($owner->name()), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $resume->isDownloadable() ? 'public, max-age=300' : 'private, no-store',
        ]);
    }
}
