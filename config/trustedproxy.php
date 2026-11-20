<?php

// Read by Illuminate\Http\Middleware\TrustProxies. Behind a reverse proxy (e.g. the
// Docker setup) set TRUSTED_PROXIES so HTTPS, client IPs and URLs are detected from
// X-Forwarded-* headers: "*" or a comma-separated list of proxy IPs. Unset = trust none.

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [

    'proxies' => match ($proxies) {
        '' => null,
        '*' => '*',
        default => array_map(trim(...), explode(',', $proxies)),
    },

];
