<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "WA Group" (Chat > Buku Telepon > WA Group) — a read-only, live browse
 * of the WhatsApp groups a connected device is a member of. There's no
 * local "groups" table: the actual list is fetched client-side from the
 * same Chat\InboxController::chats() endpoint the Inbox page and the WA
 * Template/Pesan Terjadwal recipient picker already use (filtered down
 * to chat_jid ending in "@g.us" — see App\Services\Chat\InboxService and
 * the Go backend's WaChat model for why groups don't get a dedicated
 * endpoint: they're just chats with a group-shaped JID). This
 * controller only renders the page shell; index() carries no server-side
 * data of its own.
 */
class WaGroupController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $this->companyContext($request);

        return view('chat.wa-groups.index');
    }
}
