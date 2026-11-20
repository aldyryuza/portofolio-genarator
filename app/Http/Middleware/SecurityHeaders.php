<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Video embeds are the only third-party content the site loads.
     */
    private const FRAME_SOURCES = ['https://www.youtube-nocookie.com', 'https://player.vimeo.com'];

    public function handle(Request $request, Closure $next): Response
    {
        // Vite, Livewire and our own templates read this nonce for their <script> tags.
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->is('admin', 'admin/*', 'preview/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // File responses (media, CV, exports) set their own stricter policy.
        if ($this->isHtml($response) && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        // 'unsafe-inline' is ignored by every browser that understands nonces; it only keeps very old browsers working.
        $scripts = ["'self'", "'nonce-".Vite::cspNonce()."'", "'unsafe-inline'"];
        $styles = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        // Alpine (bundled with Livewire) evaluates expressions; only Livewire pages need this.
        if ($this->usesLivewire($request)) {
            $scripts[] = "'unsafe-eval'";
        }

        if ($hotUrl = $this->viteDevServer()) {
            $scripts[] = $hotUrl;
            $styles[] = $hotUrl;
            $connect[] = $hotUrl;
            $connect[] = preg_replace('#^http#', 'ws', $hotUrl);
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scripts,
            'style-src' => $styles,
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => $connect,
            'frame-src' => self::FRAME_SOURCES,
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');

        return app()->isProduction() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    private function usesLivewire(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'login', 'forgot-password', 'reset-password/*');
    }

    private function viteDevServer(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(Vite::hotFile()));

        return preg_match('#^https?://[^\s/]+$#', $url) ? $url : null;
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
