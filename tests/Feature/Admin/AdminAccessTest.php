<?php

use App\Models\User;

dataset('admin routes', [
    'admin.dashboard',
    'admin.profile',
    'admin.account',
    'admin.projects.index',
    'admin.projects.create',
    'admin.categories',
    'admin.tags',
    'admin.skills',
    'admin.skill-groups',
    'admin.media',
    'admin.experiences',
    'admin.educations',
    'admin.certifications',
    'admin.social-links',
    'admin.resumes',
    'admin.inbox',
    'admin.exports',
    'admin.qr-codes',
    'admin.analytics',
]);

test('guests are redirected to login from admin pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('admin routes');

test('authenticated non-owner users are forbidden from admin pages', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertForbidden();
})->with('admin routes');

test('owner can open admin pages', function (string $route) {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route($route))
        ->assertOk();
})->with('admin routes');

test('admin responses are marked noindex', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('admin.dashboard'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('web responses include baseline security headers', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('X-Robots-Tag');
});
