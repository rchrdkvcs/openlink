<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsViewController;
use App\Http\Controllers\AppAssociationController;
use App\Http\Controllers\AppAssociationFileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DestinationHealthController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\InstanceDiagnosticsController;
use App\Http\Controllers\InstanceSettingsController;
use App\Http\Controllers\InstanceUpdateController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\LinkPageController;
use App\Http\Controllers\LinkPreviewImageController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileApiTokenController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileConnectedIdentityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLinkController;
use App\Http\Controllers\PublicLinkPageController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QrCodeTemplateController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\ShortLinkCsvController;
use App\Http\Controllers\ShortLinkRevisionController;
use App\Http\Controllers\ShortLinkRoutingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UtmTemplateController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSummaryController;
use App\Http\Middleware\EnsureHasWorkspace;
use App\Http\Middleware\ScopeBindingsToCurrentWorkspace;
use App\Models\LinkPage;
use App\Models\User;
use App\Services\ApplicationHost;
use App\Services\LinkPreviews\LinkPreviewImageStorage;
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

    Route::middleware(['auth', 'verified', EnsureHasWorkspace::class, ScopeBindingsToCurrentWorkspace::class])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'overview'])->name('dashboard');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
        Route::get('/analytics/open/{workspace}', [AnalyticsController::class, 'open'])->middleware('signed')->name('analytics.open');
        Route::get('/links', [DashboardController::class, 'links'])->name('links.index');
        Route::get('/links/export', [ShortLinkCsvController::class, 'export'])->name('links.export');
        Route::get('/links/import/example', [ShortLinkCsvController::class, 'example'])->name('links.import.example');
        Route::get('/links/{shortLink}/routing', [ShortLinkRoutingController::class, 'edit'])->name('links.routing');
        Route::get('/links/issues', [DestinationHealthController::class, 'index'])->name('destination-issues.index');
        Route::get('/utm-templates', [UtmTemplateController::class, 'index'])->name('utm-templates.index');
        Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
        Route::get('/link-pages', [LinkPageController::class, 'index'])->name('link-pages.index');
        Route::get('/link-pages/short-links/search', [LinkPageController::class, 'shortLinks'])->name('link-pages.short-links');
        Route::get('/link-pages/{linkPage}', [LinkPageController::class, 'show'])->name('link-pages.show');
        Route::get('/settings/domains', [DashboardController::class, 'domains'])->name('domains.index');
        Route::get('/settings/domains/new', [DomainController::class, 'create'])->name('domains.create');
        Route::get('/settings/domains/{domain}/setup', [DomainController::class, 'setup'])->name('domains.setup');
        Route::get('/settings/domains/{domain}/apps', [AppAssociationController::class, 'index'])->name('domains.apps');
        Route::get('/settings/members', [DashboardController::class, 'members'])->name('members.index');
        Route::get('/settings/workspace', [WorkspaceController::class, 'settings'])->name('settings.workspace');
        Route::get('/settings/webhooks', [WebhookEndpointController::class, 'index'])->name('settings.webhooks');
        Route::get('/settings/summaries', [WorkspaceSummaryController::class, 'show'])->name('settings.summaries');
        Route::redirect('/settings', '/settings/workspace')->name('settings');
        Route::get('/domains', fn () => redirect()->route('domains.index'));
        Route::get('/members', fn () => redirect()->route('members.index'));
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/favicons', [FaviconController::class, 'show'])->name('favicons.show');
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/onboarding/workspace', [OnboardingController::class, 'storeWorkspace'])->name('onboarding.workspace');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    });

    Route::middleware(['auth', ScopeBindingsToCurrentWorkspace::class])->group(function () {
        Route::get('/settings/instance', [DashboardController::class, 'settings'])
            ->middleware(EnsureHasWorkspace::class)
            ->name('settings.index');
        Route::get('/settings/instance/diagnostics', [InstanceDiagnosticsController::class, 'show'])
            ->middleware([EnsureHasWorkspace::class, 'can:administer-instance'])
            ->name('settings.diagnostics');
        Route::get('/settings/account', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::get('/profile', fn () => redirect()->route('profile.edit', request()->query()));
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::patch('/profile/avatar', [ProfileAvatarController::class, 'update'])->name('profile.avatar.update');
        Route::post('/profile/api-tokens', [ProfileApiTokenController::class, 'store'])->name('profile.api-tokens.store');
        Route::delete('/profile/api-tokens/{tokenId}', [ProfileApiTokenController::class, 'destroy'])->name('profile.api-tokens.destroy');
        Route::delete('/profile/connected-identities/{socialAccount}', [ProfileConnectedIdentityController::class, 'destroy'])->name('profile.connected-identities.destroy');
        Route::post('/profile/two-factor', [ProfileController::class, 'prepareTwoFactor'])->name('profile.two-factor.prepare');
        Route::post('/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
        Route::delete('/profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');

        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::get('/workspaces/{workspace}/manage', [WorkspaceController::class, 'manage'])->name('workspaces.manage');
        Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::post('/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
        Route::post('/domains/{domain}/disable', [DomainController::class, 'disable'])->name('domains.disable');
        Route::post('/domains/{domain}/transfer', [DomainController::class, 'transfer'])->name('domains.transfer');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
        Route::post('/domains/{domain}/app-associations', [AppAssociationController::class, 'store'])->name('domains.app-associations.store');
        Route::delete('/domains/{domain}/app-associations/{appAssociation}', [AppAssociationController::class, 'destroy'])
            ->scopeBindings()
            ->name('domains.app-associations.destroy');

        Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
        Route::patch('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
        Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::post('/utm-templates', [UtmTemplateController::class, 'store'])->name('utm-templates.store');
        Route::patch('/utm-templates/{utmTemplate}', [UtmTemplateController::class, 'update'])->name('utm-templates.update');
        Route::delete('/utm-templates/{utmTemplate}', [UtmTemplateController::class, 'destroy'])->name('utm-templates.destroy');

        Route::put('/workspace-summary', [WorkspaceSummaryController::class, 'update'])->name('workspace-summary.update');

        Route::post('/analytics/views', [AnalyticsViewController::class, 'store'])->name('analytics-views.store');
        Route::patch('/analytics/views/{analyticsView}', [AnalyticsViewController::class, 'update'])->name('analytics-views.update');
        Route::delete('/analytics/views/{analyticsView}', [AnalyticsViewController::class, 'destroy'])->name('analytics-views.destroy');

        Route::post('/invite-links', [InviteLinkController::class, 'store'])->name('invite-links.store');
        Route::delete('/invite-links/{inviteLink}', [InviteLinkController::class, 'destroy'])->name('invite-links.destroy');

        Route::patch('/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::post('/members/leave', [MemberController::class, 'leave'])->name('members.leave');
        Route::post('/members/{member}/transfer-ownership', [MemberController::class, 'transferOwnership'])->name('members.transfer-ownership');

        Route::post('/join/{inviteLink}', [JoinController::class, 'store'])
            ->withoutMiddleware(ScopeBindingsToCurrentWorkspace::class)
            ->name('join.store');

        Route::post('/short-links', [ShortLinkController::class, 'store'])->name('short-links.store');
        Route::post('/short-links/bulk', [ShortLinkController::class, 'bulk'])->name('short-links.bulk');
        Route::patch('/short-links/{shortLink}', [ShortLinkController::class, 'update'])->name('short-links.update');
        Route::put('/short-links/{shortLink}/routing', [ShortLinkRoutingController::class, 'update'])->name('short-links.routing.update');
        Route::post('/short-links/{shortLink}/routing-preview', [ShortLinkController::class, 'routingPreview'])->name('short-links.routing-preview');
        Route::get('/short-links/{shortLink}/preview-image', [LinkPreviewImageController::class, 'show'])->name('short-links.preview-image');
        Route::post('/short-links/{shortLink}/move', [ShortLinkController::class, 'move'])->name('short-links.move');
        Route::post('/short-links/{shortLink}/archive', [ShortLinkController::class, 'archive'])->name('short-links.archive');
        Route::delete('/short-links/{shortLink}', [ShortLinkController::class, 'destroy'])->name('short-links.destroy');
        Route::post('/short-links/import/preview', [ShortLinkCsvController::class, 'preview'])->name('short-links.import.preview');
        Route::post('/short-links/import', [ShortLinkCsvController::class, 'store'])->name('short-links.import');
        Route::get('/short-links/{shortLink}/revisions', [ShortLinkRevisionController::class, 'index'])->name('short-links.revisions.index');
        Route::get('/short-links/{shortLink}/revisions/{revision}', [ShortLinkRevisionController::class, 'show'])->whereNumber('revision')->name('short-links.revisions.show');
        Route::post('/short-links/{shortLink}/revisions/{revision}/restore', [ShortLinkRevisionController::class, 'restore'])->whereNumber('revision')->name('short-links.revisions.restore');
        Route::post('/short-links/{shortLink}/destination-checks', [DestinationHealthController::class, 'checkShortLink'])
            ->middleware('throttle:destination-checks')
            ->name('short-links.destination-checks.store');
        Route::post('/destination-checks/{destinationCheck}/retry', [DestinationHealthController::class, 'retry'])
            ->middleware('throttle:destination-checks')
            ->name('destination-checks.retry');

        Route::post('/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
        Route::get('/qr-codes/short-links/search', [QrCodeController::class, 'shortLinks'])->name('qr-codes.short-links');
        Route::get('/qr-codes/{qrCode}', [QrCodeController::class, 'show'])->name('qr-codes.show');
        Route::patch('/qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('qr-codes.update');
        Route::delete('/qr-codes/{qrCode}', [QrCodeController::class, 'destroy'])->name('qr-codes.destroy');
        Route::get('/qr-codes/{qrCode}/preview', [QrCodeController::class, 'preview'])->name('qr-codes.preview');
        Route::post('/qr-codes/{qrCode}/readability', [QrCodeController::class, 'readability'])->middleware('throttle:qr-readability')->name('qr-codes.readability');
        Route::get('/qr-codes/{qrCode}/{format}', [QrCodeController::class, 'export'])->name('qr-codes.export');

        Route::post('/qr-code-templates', [QrCodeTemplateController::class, 'store'])->name('qr-code-templates.store');
        Route::patch('/qr-code-templates/{qrCodeTemplate}', [QrCodeTemplateController::class, 'update'])->name('qr-code-templates.update');
        Route::delete('/qr-code-templates/{qrCodeTemplate}', [QrCodeTemplateController::class, 'destroy'])->name('qr-code-templates.destroy');
        Route::post('/webhook-endpoints', [WebhookEndpointController::class, 'store'])->name('webhook-endpoints.store');
        Route::patch('/webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('webhook-endpoints.update');
        Route::post('/webhook-endpoints/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhook-endpoints.rotate-secret');
        Route::post('/webhook-endpoints/{webhookEndpoint}/test', [WebhookEndpointController::class, 'test'])->middleware('throttle:10,1')->name('webhook-endpoints.test');
        Route::delete('/webhook-endpoints/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhook-endpoints.destroy');
        Route::post('/webhook-deliveries/{webhookDelivery}/retry', [WebhookEndpointController::class, 'retry'])->middleware('throttle:30,1')->name('webhook-deliveries.retry');
        Route::post('/link-pages', [LinkPageController::class, 'store'])->name('link-pages.store');
        Route::patch('/link-pages/{linkPage}', [LinkPageController::class, 'update'])->name('link-pages.update');
        Route::post('/link-pages/{linkPage}/publish', [LinkPageController::class, 'publish'])->name('link-pages.publish');
        Route::post('/link-pages/{linkPage}/unpublish', [LinkPageController::class, 'unpublish'])->name('link-pages.unpublish');
        Route::delete('/link-pages/{linkPage}', [LinkPageController::class, 'destroy'])->name('link-pages.destroy');

        Route::patch('/instance-settings', [InstanceSettingsController::class, 'update'])->name('instance-settings.update');
        Route::post('/instance-update', [InstanceUpdateController::class, 'store'])->name('instance-update.store');
        Route::post('/instance-diagnostics/recheck', [InstanceDiagnosticsController::class, 'recheck'])
            ->middleware(['can:administer-instance', 'throttle:instance-diagnostics'])
            ->name('instance-diagnostics.recheck');
        Route::post('/instance-diagnostics/test-mail', [InstanceDiagnosticsController::class, 'testMail'])
            ->middleware(['can:administer-instance', 'throttle:instance-diagnostics-mail'])
            ->name('instance-diagnostics.test-mail');
    });

    require __DIR__.'/auth.php';

    Route::get('/join/{inviteLink}', [JoinController::class, 'show'])->name('join.show');

    Route::get('/'.LinkPage::PATH_PREFIX.'/{path}', PublicLinkPageController::class)
        ->middleware('throttle:public-resolution')
        ->where('path', LinkPage::ROUTE_PATTERN)
        ->name('public.link-page');
});

Route::get('/.well-known/apple-app-site-association', [AppAssociationFileController::class, 'apple'])->middleware('throttle:public-resolution')->name('app-associations.apple');
Route::get('/.well-known/assetlinks.json', [AppAssociationFileController::class, 'android'])->middleware('throttle:public-resolution')->name('app-associations.android');

Route::get('/', [PublicLinkController::class, 'unavailable'])->middleware('throttle:public-resolution')->name('public.unavailable');
Route::get('/qr/{qrCode}', [PublicLinkController::class, 'qr'])->middleware('throttle:public-resolution')->name('public.qr');
Route::get('/_preview/{image}', [LinkPreviewImageController::class, 'public'])
    ->middleware('throttle:public-resolution')
    ->where('image', LinkPreviewImageStorage::FILENAME_PATTERN)
    ->name('public.preview-image');
Route::post('/password/{shortLink}', [PublicLinkController::class, 'password'])->middleware('throttle:public-resolution')->name('public.password');
Route::get('/{slug}', [PublicLinkController::class, 'show'])
    ->middleware('throttle:public-resolution')
    ->where('slug', '.*')
    ->name('public.short-url');
