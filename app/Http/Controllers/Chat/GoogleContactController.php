<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Google Contact" (Chat > Buku Telepon > Google Contact) — placeholder
 * page, not a real integration yet. This app already has "Sign in with
 * Google" (App\Http\Controllers\Auth\AuthController, via
 * laravel/socialite), but that only ever requests the basic
 * profile/email scope needed to identify who's logging in — it holds no
 * offline refresh token and never asks for the sensitive
 * `contacts.readonly` People API scope a real import would need.
 *
 * Wiring up an actual Google Contacts import needs, on top of code
 * changes: a second, separate OAuth consent flow scoped to
 * contacts.readonly (Google requires its own consent screen + usually a
 * verification/security-assessment review for "sensitive" scopes before
 * it can be used by real users, not just the developer's own test
 * accounts), a place to store each company's offline refresh token, and
 * a background sync job. None of that can be set up or verified from
 * here — it needs the account owner's own Google Cloud Console project.
 * Until that's configured, this page explains the gap and points users
 * to the working alternative (Import .xlsx/.csv on the Buku Telepon
 * page) instead of a dead link.
 */
class GoogleContactController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $this->companyContext($request);

        return view('chat.google-contacts.index');
    }
}
