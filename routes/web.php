<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\QrCodeDownloadController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\ResumeController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Middleware\TrackPageView;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public site: read-only, published content only. Page views here feed the
// cookie-less analytics (previews, admin, media and crawler endpoints are not counted).
Route::middleware(TrackPageView::class)->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/experience', [PageController::class, 'experience'])->name('experience');
    Route::get('/education', [PageController::class, 'education'])->name('education');
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('projects.show');
    Route::get('/cv', [ResumeController::class, 'index'])->name('cv');
    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
});
Route::get('/preview/projects/{project}', [ProjectController::class, 'preview'])
    ->middleware(['signed', 'throttle:60,1'])
    ->name('projects.preview');
Route::get('/cv/{resume}/download', [ResumeController::class, 'download'])
    ->whereUuid('resume')
    ->middleware('throttle:30,1')
    ->name('cv.download');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
// Crawler endpoints need no session, cookies or CSRF; skipping them avoids a session row per bot hit.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
});

// Owner authentication. There is intentionally no registration route.
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Authorization (public vs owner-only) is enforced inside the controller via MediaAssetPolicy.
Route::get('/media/{media}/{variant}', MediaController::class)
    ->whereUuid('media')
    ->whereIn('variant', ['large', 'thumb', 'original'])
    ->middleware('throttle:240,1')
    ->name('media.show');

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

        Route::livewire('/projects', 'pages::admin.projects.index')->name('projects.index');
        Route::livewire('/projects/create', 'pages::admin.projects.form')->name('projects.create');
        Route::livewire('/projects/{project}/edit', 'pages::admin.projects.form')->name('projects.edit');
        Route::livewire('/projects/{project}/media', 'pages::admin.projects.media')->name('projects.media');

        Route::livewire('/media', 'pages::admin.media')->name('media');

        Route::livewire('/experiences', 'pages::admin.experiences')->name('experiences');
        Route::livewire('/educations', 'pages::admin.educations')->name('educations');
        Route::livewire('/certifications', 'pages::admin.certifications')->name('certifications');
        Route::livewire('/social-links', 'pages::admin.social-links')->name('social-links');

        Route::livewire('/resumes', 'pages::admin.resumes')->name('resumes');
        Route::livewire('/inbox', 'pages::admin.inbox')->name('inbox');

        Route::livewire('/exports', 'pages::admin.exports')->name('exports');
        Route::livewire('/qr-codes', 'pages::admin.qr-codes')->name('qr-codes');
        Route::livewire('/analytics', 'pages::admin.analytics')->name('analytics');
        Route::get('/qr-codes/download', QrCodeDownloadController::class)->name('qr-codes.download');
        Route::get('/exports/{exportJob}/download', ExportDownloadController::class)->whereUuid('exportJob')->name('exports.download');

        Route::livewire('/categories', 'pages::admin.categories')->name('categories');
        Route::livewire('/tags', 'pages::admin.tags')->name('tags');
        Route::livewire('/skills', 'pages::admin.skills')->name('skills');
        Route::livewire('/skill-groups', 'pages::admin.skill-groups')->name('skill-groups');

        Route::livewire('/profile', 'pages::admin.profile')->name('profile');
        Route::livewire('/account', 'pages::admin.account')->name('account');
    });
