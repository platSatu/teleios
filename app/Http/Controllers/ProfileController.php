<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Profile" in the header dropdown (resources/views/layouts/partials/
 * header.blade.php) — used to be a dead static link (pages-profile.html).
 *
 * Deliberately narrow scope, per request: a user can change their name
 * and photo here. Email is intentionally NEVER accepted from this form
 * (not even read from the request) — it's shown read-only in the view.
 * Password lives on the existing Auth\PasswordController (route
 * `password.update`, already wired to $request->user()); PIN lives on
 * User\Settings\PinController — both linked from this page rather than
 * duplicated here.
 *
 * Every read/write below goes through Auth::user() / $request->user(),
 * never a route-supplied id, so there's no way to end up editing (or
 * even viewing) another user's profile by mistake.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('image')) {
            // Remove the old photo first so changing avatars doesn't
            // silently pile up orphaned files on disk forever.
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }

            $validated['image'] = $request->file('image')->store('avatars', 'public');
        } else {
            // No new file uploaded — don't overwrite the existing path
            // with null just because the field was absent from this
            // submission.
            unset($validated['image']);
        }

        $user->update($validated);

        return back()->with('status', 'profile-updated');
    }
}
