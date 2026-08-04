<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Same mail content/URL-building logic as Laravel's stock
 * Illuminate\Auth\Notifications\ResetPassword (route('password.reset',
 * ...), config('auth.passwords.users.expire') minutes, etc.) — the only
 * change is `implements ShouldQueue`, so App\Models\User::
 * sendPasswordResetNotification() (overridden to dispatch this instead
 * of the base class) queues the email instead of sending it inline
 * during the forgot-password request.
 */
class ResetPasswordNotification extends BaseResetPasswordNotification implements ShouldQueue
{
    use Queueable;
}
