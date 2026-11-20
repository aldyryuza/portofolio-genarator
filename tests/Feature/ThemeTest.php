<?php

use App\Models\User;

dataset('themed pages', [
    'home' => [fn () => test()->get('/')],
    'project list' => [fn () => test()->get('/projects')],
    'login' => [fn () => test()->get('/login')],
    'admin' => [fn () => test()->actingAs(User::factory()->owner()->create())->get('/admin')],
    'error page' => [fn () => test()->get('/projects/missing-project')],
]);

test('the theme is applied by a nonce-protected script before the stylesheet loads', function (Closure $request) {
    $response = $request();
    $html = $response->getContent();

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $nonce);
    $scriptPosition = strpos($html, "document.documentElement.setAttribute('data-theme'");
    $stylesheetPosition = strpos($html, 'rel="stylesheet"') ?: strpos($html, 'rel="preload"');

    expect($scriptPosition)->not->toBeFalse()
        ->and($stylesheetPosition === false || $scriptPosition < $stylesheetPosition)->toBeTrue();

    if ($nonce !== []) {
        expect($html)->toContain('<script nonce="'.$nonce[1].'">');
    }
})->with('themed pages');

test('pages offer an accessible theme toggle that is hidden until JavaScript runs', function (Closure $request) {
    $html = $request()->getContent();

    expect($html)->toContain('data-theme-toggle hidden aria-pressed="false"')
        ->toContain('<span class="sr-only">Dark mode</span>');
})->with([
    'home' => [fn () => test()->get('/')],
    'login' => [fn () => test()->get('/login')],
    'admin' => [fn () => test()->actingAs(User::factory()->owner()->create())->get('/admin')],
]);
