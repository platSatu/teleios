<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/**
 * Login / register / forgot-password / reset-password / email-verification
 * / logout all now live in one App\Http\Controllers\Auth\AuthController —
 * see that class's docblock for the business rules layered on top of
 * stock Breeze. Route names below are unchanged from the original split
 * controllers so no Blade view or other route reference needed updating.
 *
 * verification.notice/verify/send moved out of the `auth` middleware
 * group into the `guest` one: this app's inactive users can't log in at
 * all, so there's no authenticated session to gate these behind — they
 * have to be reachable by a guest holding nothing but an email or a
 * emailed link. verification.verify also changed from Laravel's stock
 * signed {id}/{hash} URL to a single {token} matched against the
 * users.email_verification_token column (see AuthController::verifyEmail()
 * for why: a signed URL can't be told "expired" from "wrong", and can't
 * be looked up again for a resend).
 *
 * ConfirmablePasswordController / PasswordController (confirm-password,
 * password.update) are untouched — a separate concern from the rest of
 * this file.
 */
Route::prefix('auth')->middleware('guest')->group(function () {
    Route::get('register', [AuthController::class, 'showRegister'])
        ->name('register');

    Route::post('register', [AuthController::class, 'register']);

    Route::get('login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('login', [AuthController::class, 'login']);

    // "Sign in/up with Google" — see AuthController::redirectToGoogle()/
    // handleGoogleCallback(). Same guest-only group as login/register
    // since this is another way to reach the exact same outcome.
    Route::get('google', [AuthController::class, 'redirectToGoogle'])
        ->name('auth.google');

    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback'])
        ->name('auth.google.callback');

    Route::get('forgot-password', [AuthController::class, 'showForgotPassword'])
        ->name('password.request');

    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('password.email');

    Route::get('reset-password/{token}', [AuthController::class, 'showResetPassword'])
        ->name('password.reset');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.store');

    Route::get('verify-email', [AuthController::class, 'showResendVerification'])
        ->name('verification.notice');

    Route::get('verify-email/{token}', [AuthController::class, 'verifyEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');

    Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::prefix('auth')->middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthController::class, 'logout'])
        ->name('logout');
});
