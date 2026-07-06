<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FolderPermissionController;
use App\Http\Controllers\InstanceSettingsController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use App\Services\ApplicationHost;
use Illuminate\Support\Facades\Route;

Route::domain(app(ApplicationHost::class)->host())->group(function () {
    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return User::query()->exists()
            ? redirect()->route('login')
            : redirect()->route('register');
    })->name('home');

    Route::get('/dashboard', [DashboardController::class, 'overview'])->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware(['auth', 'verified'])->name('analytics.index');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->middleware(['auth', 'verified'])->name('analytics.export');
    Route::get('/links', [DashboardController::class, 'links'])->middleware(['auth', 'verified'])->name('links.index');
    Route::get('/favicons', [FaviconController::class, 'show'])->middleware(['auth', 'verified'])->name('favicons.show');
    Route::get('/domains', [DashboardController::class, 'domains'])->middleware(['auth', 'verified'])->name('domains.index');
    Route::get('/members', [DashboardController::class, 'members'])->middleware(['auth', 'verified'])->name('members.index');
    Route::get('/workspaces', [DashboardController::class, 'workspaces'])->middleware(['auth', 'verified'])->name('workspaces.index');
    Route::get('/settings', [DashboardController::class, 'settings'])->middleware(['auth', 'verified'])->name('settings.index');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::post('/profile/two-factor', [ProfileController::class, 'prepareTwoFactor'])->name('profile.two-factor.prepare');
        Route::post('/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
        Route::delete('/profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');

        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::patch('/workspaces/current', [WorkspaceController::class, 'update'])->name('workspaces.update-current');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::post('/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::post('/domains/{domain}/disable', [DomainController::class, 'disable'])->name('domains.disable');
        Route::post('/domains/{domain}/transfer', [DomainController::class, 'transfer'])->name('domains.transfer');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
        Route::post('/folders/{folder}/permissions', [FolderPermissionController::class, 'store'])->name('folder-permissions.store');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

        Route::post('/short-links', [ShortLinkController::class, 'store'])->name('short-links.store');
        Route::patch('/short-links/{shortLink}', [ShortLinkController::class, 'update'])->name('short-links.update');
        Route::post('/short-links/{shortLink}/move', [ShortLinkController::class, 'move'])->name('short-links.move');
        Route::post('/short-links/{shortLink}/archive', [ShortLinkController::class, 'archive'])->name('short-links.archive');
        Route::delete('/short-links/{shortLink}', [ShortLinkController::class, 'destroy'])->name('short-links.destroy');

        Route::post('/short-links/{shortLink}/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
        Route::get('/qr-codes/{qrCode}', [QrCodeController::class, 'show'])->name('qr-codes.show');
        Route::patch('/qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('qr-codes.update');
        Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy'])->name('qr-codes.destroy');
        Route::get('/qr-codes/{qrCode}/preview', [QrCodeController::class, 'preview'])->name('qr-codes.preview');
        Route::get('/qr-codes/{qrCode}/{format}', [QrCodeController::class, 'export'])->name('qr-codes.export');

        Route::patch('/instance-settings', [InstanceSettingsController::class, 'update'])->name('instance-settings.update');
    });

    require __DIR__.'/auth.php';

    Route::get('/invitations/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
});

Route::get('/', [PublicLinkController::class, 'unavailable'])->middleware('throttle:public-resolution')->name('public.unavailable');
Route::get('/qr/{qrCode}', [PublicLinkController::class, 'qr'])->middleware('throttle:public-resolution')->name('public.qr');
Route::post('/password/{shortLink}', [PublicLinkController::class, 'password'])->middleware('throttle:public-resolution')->name('public.password');
Route::get('/{slug}', [PublicLinkController::class, 'show'])
    ->middleware('throttle:public-resolution')
    ->where('slug', '.*')
    ->name('public.short-url');
