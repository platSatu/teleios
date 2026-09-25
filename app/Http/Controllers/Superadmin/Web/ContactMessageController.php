<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\WebContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin > Web > Pesan Masuk: pesan dari form Kontak website.
 * Hanya baca, ubah status (Baru / Sudah dibalas / Arsip), dan hapus.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->value();
        $search = $request->string('search')->value();

        $messages = WebContactMessage::query()
            ->when(isset(WebContactMessage::STATUSES[$status]), fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = WebContactMessage::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('superadmin.web.contact-messages.index', compact('messages', 'counts', 'status', 'search'));
    }

    public function show(string $id): View
    {
        return view('superadmin.web.contact-messages.show', ['message' => CrudAdmin::find(WebContactMessage::class, $id)]);
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(WebContactMessage::STATUSES))]]);

        CrudAdmin::update(WebContactMessage::class, $id, $data);

        return back()->with('success', 'Status pesan diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebContactMessage::class, $id);

        return redirect()->route('web.contact-messages.index')->with('success', 'Pesan dihapus.');
    }
}
