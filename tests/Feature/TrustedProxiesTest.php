<?php

test('forwarded headers are ignored unless proxies are trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $this->get('/', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('<link rel="canonical" href="http://localhost', escape: false);
});

test('trusted proxies make the app honour X-Forwarded-Proto', function () {
    config(['trustedproxy.proxies' => '*']);

    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'portfolio.example.com'])
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://portfolio.example.com', escape: false);
});
