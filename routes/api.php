<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Superadmin\UserController;
use App\Http\Controllers\Api\WaIncomingMessageWebhookController;
use App\Http\Controllers\Api\WaMessageStatusWebhookController;
use App\Http\Controllers\Api\WaPollVoteWebhookController;
use App\Http\Controllers\Api\WaApiSendMessageController;
use App\Http\Controllers\Api\GoogleFormWebhookController;
use App\Http\Controllers\Api\Frontend\ArticleController as FrontendArticleController;
use App\Http\Controllers\Api\Frontend\CategoryApplicationController as FrontendCategoryApplicationController;
use App\Http\Controllers\Api\Frontend\CategoryVideoController as FrontendCategoryVideoController;
use App\Http\Controllers\Api\Frontend\FaqController as FrontendFaqController;
use App\Http\Controllers\Api\Frontend\FeatureController as FrontendFeatureController;
use App\Http\Controllers\Api\Frontend\FooterController as FrontendFooterController;
use App\Http\Controllers\Api\Frontend\HeaderController as FrontendHeaderController;
use App\Http\Controllers\Api\Frontend\PackageController as FrontendPackageController;
use App\Http\Controllers\Api\Frontend\TermConditionController as FrontendTermConditionController;
use App\Http\Controllers\Api\Frontend\VideoController as FrontendVideoController;
use App\Http\Controllers\Api\Frontend\VisitorLogController as FrontendVisitorLogController;
use App\Http\Controllers\Api\Frontend\WebSettingController as FrontendWebSettingController;
use App\Http\Controllers\User\Deposit\DuitkuCallbackController;

// Server-to-server webhook Duitku posts payment results to — resolves
// to POST /api/duitku/callback (this file is auto-prefixed with "api"
// and the stateless "api" middleware group, so no CSRF token is
// expected here in the first place — unlike routes/web.php, nothing
// needs to be exempted). Trust comes entirely from the signature check
// inside the controller. See App\Http\Controllers\User\Deposit\
// DuitkuCallbackController and App\Services\Payment\DuitkuService
// (builds the callbackUrl sent to Duitku via route('deposit.duitku.callback')).
Route::post('/duitku/callback', [DuitkuCallbackController::class, 'handle'])
    ->name('deposit.duitku.callback');

// Server-to-server webhook the Go backend posts to every time a WhatsApp
// message actually arrives (see g_backend's WaInboxService.
// notifyIncomingMessageWebhook) — drives "Auto Reply (Kata Kunci)":
// matches the message body against every active WaMessageAutoReply for
// that device and replies if one matches. Trust comes from the shared
// X-API-KEY (see App\Http\Middleware\VerifyGolangApiKey), same as every
// Laravel -> Go call does in reverse.
Route::post('/webhooks/wa/incoming-message', [WaIncomingMessageWebhookController::class, 'handle'])
    ->middleware('golang.api-key')
    ->name('webhooks.wa.incoming-message');

// Server-to-server webhook the Go backend posts to every time a sent
// message's delivery/read receipt arrives (see g_backend's
// WaInboxService.notifyMessageStatusWebhook, fired from
// UpdateMessageStatus() as *events.Receipt events come in from whatsmeow)
// — drives real Delivered/Read tracking on the Pesan Terjadwal history
// page. See App\Http\Controllers\Api\WaMessageStatusWebhookController.
// Same trust model as the incoming-message webhook above (shared
// X-API-KEY via golang.api-key middleware).
Route::post('/webhooks/wa/message-status', [WaMessageStatusWebhookController::class, 'handle'])
    ->middleware('golang.api-key')
    ->name('webhooks.wa.message-status');

// Server-to-server webhook the Go backend posts to every time a WhatsApp
// poll vote arrives (see g_backend's WaInboxService.notifyPollVoteWebhook,
// fired from handlePollVote() right after it decrypts the vote) — drives
// Fitur #7's CSAT survey scoring. See App\Http\Controllers\Api\
// WaPollVoteWebhookController. Same trust model as the two webhooks
// above (shared X-API-KEY via golang.api-key middleware).
Route::post('/webhooks/wa/poll-vote', [WaPollVoteWebhookController::class, 'handle'])
    ->middleware('golang.api-key')
    ->name('webhooks.wa.poll-vote');

// Public third-party WhatsApp send API — authenticated purely by the
// per-device token/secret_key pair (App\Http\Middleware\VerifyWaApiKey),
// no logged-in user or session involved at all. See App\Models\WaApiKey
// and App\Http\Controllers\Api\WaApiSendMessageController. Full usage
// docs are published at GET /dokumentasi (no login required).
Route::post('/wa-api/v1/send-message', [WaApiSendMessageController::class, 'send'])
    ->middleware('wa.api-key')
    ->name('wa-api.send-message');

// Public webhook a company's own Google Apps Script (Extensions > Apps
// Script > onFormSubmit trigger, generated on the integration's detail
// page) POSTs to on every new Google Form response. Authenticated purely
// by the unguessable {token} path segment — see App\Models\
// WaFormIntegration::generateUniqueWebhookToken() and
// App\Http\Controllers\Api\GoogleFormWebhookController. Throttled since
// it's unauthenticated-by-header and reachable by anyone who has (or
// guesses) a token.
Route::post('/third-party/google-form/{token}', [GoogleFormWebhookController::class, 'receive'])
    ->middleware('throttle:60,1')
    ->name('third-party.google-form.receive');

// Public read-only catalog the fe-konexa frontend (separate Laravel
// app, running on its own `php artisan serve` port — see .env's
// SERVER_PORT / FRONTEND_API_URL) calls server-to-server to render its
// landing/product pages. Gated by the shared X-API-KEY secret
// (frontend.api-key -> VerifyFrontendApiKey), same trust model as
// golang.api-key above — no logged-in user involved on that side.
Route::prefix('frontend')->middleware('frontend.api-key')->group(function () {
    Route::get('/category-applications', [FrontendCategoryApplicationController::class, 'index'])
        ->name('api.frontend.category-applications.index');

    Route::get('/packages', [FrontendPackageController::class, 'index'])
        ->name('api.frontend.packages.index');

    Route::get('/articles', [FrontendArticleController::class, 'index'])
        ->name('api.frontend.articles.index');

    Route::get('/faqs', [FrontendFaqController::class, 'index'])
        ->name('api.frontend.faqs.index');

    Route::get('/term-condition', [FrontendTermConditionController::class, 'show'])
        ->name('api.frontend.term-condition.show');

    Route::get('/category-videos', [FrontendCategoryVideoController::class, 'index'])
        ->name('api.frontend.category-videos.index');

    Route::get('/videos', [FrontendVideoController::class, 'index'])
        ->name('api.frontend.videos.index');

    Route::get('/web-setting', [FrontendWebSettingController::class, 'show'])
        ->name('api.frontend.web-setting.show');

    Route::get('/features', [FrontendFeatureController::class, 'index'])
        ->name('api.frontend.features.index');

    Route::get('/headers', [FrontendHeaderController::class, 'index'])
        ->name('api.frontend.headers.index');

    Route::get('/footers', [FrontendFooterController::class, 'index'])
        ->name('api.frontend.footers.index');

    // Satu-satunya endpoint TULIS di grup ini (yang lain semua baca
    // katalog) — fe-konexa lapor tiap kunjungan halaman publik ke sini
    // lewat App\Http\Middleware\LogVisitorMiddleware di app itu. Gerbang
    // keamanannya sama persis dengan endpoint baca di atas (X-API-KEY),
    // jadi tidak bisa dipanggil bebas dari browser pengunjung.
    Route::post('/visitor-log', [FrontendVisitorLogController::class, 'store'])
        ->name('api.frontend.visitor-log.store');
});

Route::prefix('superadmin')->middleware('auth:sanctum')->group(function () {

    Route::prefix('users')
        ->controller(UserController::class)
        ->group(function () {
            Route::get('/', 'index')->name('users.index');
    });

});


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [
    \App\Http\Controllers\Api\Auth\AuthController::class,
    'login'
]);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [
        \App\Http\Controllers\Api\Auth\AuthController::class,
        'logout'
    ]);

});
