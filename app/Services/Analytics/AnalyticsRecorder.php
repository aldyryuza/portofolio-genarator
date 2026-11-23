<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cookie-less, first-party analytics.
 *
 * Privacy rules: no cookies, no stored IP address or user agent. A visitor is a
 * keyed hash of (day, IP, user agent) that rotates daily, so visits cannot be
 * linked across days. The owner, bots, and visitors sending Do-Not-Track or
 * Global Privacy Control are never recorded.
 */
class AnalyticsRecorder
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|monitor|lighthouse|headless|pagespeed|facebookexternalhit|whatsapp|telegram|curl|wget|python|httpclient|java\/|go-http/i';

    /**
     * @param  array{project_id?: int|null, resume_id?: int|null}  $context
     */
    public function record(AnalyticsEventType $type, Request $request, array $context = []): void
    {
        if (! $this->shouldTrack($request)) {
            return;
        }

        try {
            AnalyticsEvent::create([
                'type' => $type,
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
                'project_id' => $context['project_id'] ?? null,
                'resume_id' => $context['resume_id'] ?? null,
                'referrer_host' => $this->referrerHost($request),
                'source' => $this->source($request),
                'device' => preg_match('/Mobi|Android|iPhone|iPad/i', (string) $request->userAgent()) ? 'mobile' : 'desktop',
                'visitor_hash' => $this->visitorHash($request),
            ]);
        } catch (Throwable $exception) {
            // Analytics must never break a page or a download.
            report($exception);
        }
    }

    public function shouldTrack(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        return $userAgent !== ''
            && ! preg_match(self::BOT_PATTERN, $userAgent)
            && $request->header('DNT') !== '1'
            && $request->header('Sec-GPC') !== '1'
            && $request->user()?->is_owner !== true;
    }

    /**
     * Campaign marker from ?ref=… (e.g. "qr" on printed QR codes).
     */
    private function source(Request $request): ?string
    {
        $ref = $request->query('ref');

        return is_string($ref) && preg_match('/^[a-z0-9-]{1,30}$/', $ref) ? $ref : null;
    }

    /**
     * Only the host of an external referrer; the full URL may contain personal data.
     */
    private function referrerHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        if (! is_string($host) || $host === '' || strcasecmp($host, $request->getHost()) === 0) {
            return null;
        }

        return Str::limit(strtolower(preg_replace('/^www\./i', '', $host) ?? $host), 100, '');
    }

    private function visitorHash(Request $request): string
    {
        return hash_hmac('sha256', now()->toDateString().'|'.$request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }
}
