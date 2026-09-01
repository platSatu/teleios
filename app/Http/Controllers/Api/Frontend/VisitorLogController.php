<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\FrontendVisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

/**
 * Menerima laporan kunjungan dari fe-konexa (server-to-server, lewat
 * App\Http\Middleware\LogVisitorMiddleware di app itu) untuk setiap
 * halaman publik yang dibuka orang — beranda/artikel/syarat-dan-
 * ketentuan/video/kontak. Gated oleh VerifyFrontendApiKey yang sama
 * dengan endpoint /api/frontend/* lain (lihat routes/api.php's
 * `frontend.api-key` group) — bukan endpoint yang bisa dipanggil bebas
 * dari browser pengunjung, cuma dari server fe-konexa yang tahu
 * kuncinya.
 *
 * browser/browser_version/os/device_type sengaja DIPARSE DI SINI dari
 * user_agent mentah yang dikirim fe-konexa (pakai jenssegers/agent),
 * bukan dipercaya kalau fe-konexa yang kirim sudah-jadi — supaya
 * logika parsing-nya cuma ada di satu tempat dan konsisten kalau nanti
 * ada sumber lain yang ikut lapor.
 */
class VisitorLogController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => ['required', 'string', 'max:64'],
            'ip_address' => ['required', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:512'],
            'path' => ['required', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:255'],
            'visited_at' => ['required', 'date'],
        ]);

        $agent = new Agent();
        $agent->setUserAgent($validated['user_agent'] ?? '');

        $browser = $agent->browser() ?: null;

        $log = FrontendVisitorLog::create([
            'visitor_id' => $validated['visitor_id'],
            'ip_address' => $validated['ip_address'],
            'user_agent' => $validated['user_agent'] ?? null,
            'browser' => $browser,
            'browser_version' => $browser ? ($agent->version($browser) ?: null) : null,
            'os' => $agent->platform() ?: null,
            'device_type' => $this->deviceType($agent),
            'path' => $validated['path'],
            'referrer' => $validated['referrer'] ?? null,
            'visited_at' => $validated['visited_at'],
        ]);

        return response()->json(['data' => ['id' => $log->id]], 201);
    }

    private function deviceType(Agent $agent): string
    {
        return match (true) {
            $agent->isRobot() => 'bot',
            $agent->isTablet() => 'tablet',
            $agent->isMobile() => 'mobile',
            default => 'desktop',
        };
    }
}
