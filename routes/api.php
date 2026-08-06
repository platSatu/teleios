<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Superadmin\UserController;
use App\Http\Controllers\Api\WaIncomingMessageWebhookController;
use App\Http\Controllers\Api\WaMessageStatusWebhookController;
use App\Http\Controllers\Api\WaApiSendMessageController;
use App\Http\Controllers\Api\GoogleFormWebhookController;
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
