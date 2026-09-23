<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Services\Chat\ConnectDeviceService;
use App\Services\Chat\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Throwable;

/**
 * Powers the header bell's real "pesan baru masuk" notifications — before
 * this existed, that dropdown (resources/views/layouts/partials/header.
 * blade.php) was 100% static demo markup from the admin theme (fake
 * names like "Naomi"/"Robert", hardcoded badge counts), completely
 * disconnected from any real chat data.
 *
 * There's no single "all my unread chats" endpoint anywhere in this app —
 * every existing Chat route is scoped to one {device} at a time (see
 * Chat\InboxController) — so this loops every device the logged-in user
 * owns and merges each one's unread chats into one list. Best-effort per
 * device: one unreachable/disconnected device must never blank out the
 * whole bell for the rest.
 */
class NotificationController extends Controller
{
    /** Keeps the dropdown short and this endpoint's per-poll cost bounded. */
    private const MAX_NOTIFICATIONS = 20;

    public function unread(ConnectDeviceService $devices, InboxService $inbox): JsonResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        try {
            $deviceList = $devices->listDevices($jwt);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $notifications = [];

        foreach ($deviceList as $device) {
            $deviceId = $device['id'] ?? null;

            if (! $deviceId) {
                continue;
            }

            try {
                $chats = $inbox->chats($jwt, $deviceId);
            } catch (Throwable $e) {
                // One device being slow/disconnected shouldn't blank the
                // bell for every other device the user has — skip it.
                continue;
            }

            foreach ($chats as $chat) {
                $chatJid = $chat['chat_jid'] ?? '';

                // A WhatsApp Channel (e.g. "Tribunnews.com", "BMKG")
                // broadcasts to everyone following it, not a real
                // conversation with this company — Inbox already hides
                // these from the chat list itself (inbox.blade.php's
                // classifyChat()/'channel' filter), but that's a
                // client-side-only filter, so this endpoint (which reads
                // the same raw chats($jwt, $deviceId) list) was showing
                // them here regardless. Same str_ends_with('@newsletter')
                // check InboxController::contact() already uses, so a
                // channel can never surface an unread "message" that
                // isn't really a 1:1/group conversation (23 September
                // 2026 request — the bell should track real incoming
                // chats the same way Inbox does).
                if (str_ends_with($chatJid, '@newsletter')) {
                    continue;
                }

                $unreadCount = (int) ($chat['unread_count'] ?? 0);

                if ($unreadCount < 1) {
                    continue;
                }

                $notifications[] = [
                    'device_id' => $deviceId,
                    'chat_jid' => $chatJid,
                    'name' => $chat['name'] ?: ($chatJid ?: 'Kontak'),
                    'avatar_url' => $chat['avatar_url'] ?? null,
                    'last_message' => Str::limit((string) ($chat['last_message'] ?? ''), 80),
                    'last_message_at' => $chat['last_message_at'] ?? null,
                    'unread_count' => $unreadCount,
                ];
            }
        }

        // Newest activity first, same ordering the Inbox chat list itself
        // uses — plain string comparison is safe here since
        // last_message_at always comes through as an ISO 8601 timestamp
        // from the Go backend.
        usort($notifications, fn (array $a, array $b) => strcmp((string) $b['last_message_at'], (string) $a['last_message_at']));

        $notifications = array_slice($notifications, 0, self::MAX_NOTIFICATIONS);

        return response()->json([
            'notifications' => $notifications,
            // Count of conversations with unread messages, not the sum of
            // unread_count across them — matches what the list below
            // actually shows one row per, and keeps the bell's badge from
            // jumping around based on how many messages someone sent in a
            // single burst.
            'unread_count' => count($notifications),
        ]);
    }
}
