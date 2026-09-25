<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chat\WaApiUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/wa-api/v1/usage — pihak ketiga bisa mengecek sendiri status
 * paket, sisa kuota pengiriman, dan jumlah request API key-nya tanpa
 * perlu login dashboard. Otentikasinya sama persis dengan send-message
 * (middleware wa.api-key / App\Http\Middleware\VerifyWaApiKey), dan
 * hanya bisa melihat data API key-nya SENDIRI.
 *
 * Read-only: tidak memotong kuota dan tidak ikut dicatat sebagai
 * request pengiriman di App\Models\WaApiRequestLog.
 *
 * Dokumentasi: docs/api/wa-api-v1.openapi.json (operationId getWaApiUsage).
 */
class WaApiUsageController extends Controller
{
    public function show(Request $request, WaApiUsageService $usage): JsonResponse
    {
        $apiKey = $request->attributes->get('waApiKey');

        $summary = $usage->summary($apiKey);

        return response()->json([
            'device_id' => $apiKey->device_id,
            'package_active' => $summary['package'] !== null,
            'package' => $summary['package'],
            'quota' => $summary['quota'],
            'requests' => $summary['api_key'],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
