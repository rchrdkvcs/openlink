<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\DomainController;
use App\Http\Controllers\Api\V1\FolderController;
use App\Http\Controllers\Api\V1\InstanceSettingsController;
use App\Http\Controllers\Api\V1\InviteLinkController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QrCodeController;
use App\Http\Controllers\Api\V1\ShortLinkController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Token-based API (Laravel Sanctum) exposing the same functionality as the
| web interface, for browser extensions and other API clients.
|
| Stateless clients select the active workspace per request with the
| `X-Workspace-Id` header; without it, the user's first workspace is used.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('auth.token');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/tokens', [AuthTokenController::class, 'index'])->name('auth.tokens.index');
        Route::delete('/auth/token', [AuthTokenController::class, 'destroyCurrent'])->name('auth.token.destroy-current');
        Route::delete('/auth/tokens/{tokenId}', [AuthTokenController::class, 'destroy'])->name('auth.tokens.destroy');

        Route::get('/me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('/me', [ProfileController::class, 'update'])->name('me.update');
        Route::delete('/me', [ProfileController::class, 'destroy'])->name('me.destroy');
        Route::put('/me/password', [ProfileController::class, 'updatePassword'])->name('me.password');
        Route::post('/me/two-factor', [ProfileController::class, 'prepareTwoFactor'])->name('me.two-factor.prepare');
        Route::post('/me/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('me.two-factor.confirm');
        Route::delete('/me/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('me.two-factor.disable');

        Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::get('/workspaces/current', [WorkspaceController::class, 'current'])->name('workspaces.current');
        Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::post('/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::post('/domains/{domain}/disable', [DomainController::class, 'disable'])->name('domains.disable');
        Route::post('/domains/{domain}/transfer', [DomainController::class, 'transfer'])->name('domains.transfer');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

        Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
        Route::post('/folders/{folder}/permissions', [FolderController::class, 'storePermission'])->name('folder-permissions.store');

        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');

        Route::get('/links', [ShortLinkController::class, 'index'])->name('links.index');
        Route::post('/links', [ShortLinkController::class, 'store'])->name('links.store');
        Route::get('/links/{shortLink}', [ShortLinkController::class, 'show'])->name('links.show');
        Route::patch('/links/{shortLink}', [ShortLinkController::class, 'update'])->name('links.update');
        Route::post('/links/{shortLink}/move', [ShortLinkController::class, 'move'])->name('links.move');
        Route::post('/links/{shortLink}/archive', [ShortLinkController::class, 'archive'])->name('links.archive');
        Route::delete('/links/{shortLink}', [ShortLinkController::class, 'destroy'])->name('links.destroy');

        Route::post('/links/{shortLink}/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
        Route::patch('/qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('qr-codes.update');
        Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy'])->name('qr-codes.destroy');
        Route::get('/qr-codes/{qrCode}/preview', [QrCodeController::class, 'preview'])->name('qr-codes.preview');
        Route::get('/qr-codes/{qrCode}/export/{format}', [QrCodeController::class, 'export'])->name('qr-codes.export');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/invite-links', [InviteLinkController::class, 'index'])->name('invite-links.index');
        Route::post('/invite-links', [InviteLinkController::class, 'store'])->name('invite-links.store');
        Route::delete('/invite-links/{inviteLink}', [InviteLinkController::class, 'destroy'])->name('invite-links.destroy');
        Route::post('/invite-links/{inviteLink}/join', [InviteLinkController::class, 'join'])->name('invite-links.join');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('/instance-settings', [InstanceSettingsController::class, 'show'])->name('instance-settings.show');
        Route::patch('/instance-settings', [InstanceSettingsController::class, 'update'])->name('instance-settings.update');
    });
});
