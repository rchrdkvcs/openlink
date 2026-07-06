<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FolderPermissionController;
use App\Http\Controllers\InstanceSettingsController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureHasWorkspace;
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

    Route::middleware(['auth', 'verified', EnsureHasWorkspace::class])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'overview'])->name('dashboard');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
        Route::get('/links', [DashboardController::class, 'links'])->name('links.index');
        Route::get('/domains', [DashboardController::class, 'domains'])->name('domains.index');
        Route::get('/members', [DashboardController::class, 'members'])->name('members.index');
        Route::get('/workspaces', [DashboardController::class, 'workspaces'])->name('workspaces.index');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('settings.index');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/favicons', [FaviconController::class, 'show'])->name('favicons.show');
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/onboarding/workspace', [OnboardingController::class, 'storeWorkspace'])->name('onboarding.workspace');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    });

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

        Route::post('/invite-links', [InviteLinkController::class, 'store'])->name('invite-links.store');
        Route::delete('/invite-links/{inviteLink}', [InviteLinkController::class, 'destroy'])->name('invite-links.destroy');

        Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::post('/members/leave', [MemberController::class, 'leave'])->name('members.leave');
        Route::post('/members/{member}/transfer-ownership', [MemberController::class, 'transferOwnership'])->name('members.transfer-ownership');

        Route::post('/join/{inviteLink}', [JoinController::class, 'store'])->name('join.store');

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

    Route::get('/join/{inviteLink}', [JoinController::class, 'show'])->name('join.show');
});

Route::get('/', [PublicLinkController::class, 'unavailable'])->middleware('throttle:public-resolution')->name('public.unavailable');
Route::get('/qr/{qrCode}', [PublicLinkController::class, 'qr'])->middleware('throttle:public-resolution')->name('public.qr');
Route::post('/password/{shortLink}', [PublicLinkController::class, 'password'])->middleware('throttle:public-resolution')->name('public.password');
Route::get('/{slug}', [PublicLinkController::class, 'show'])
    ->middleware('throttle:public-resolution')
    ->where('slug', '.*')
    ->name('public.short-url');
