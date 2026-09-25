<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaApiKey;
use App\Models\WaApiRequestLog;
use App\Services\Chat\WaApiUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Superadmin > Pemakaian WA API — melihat riwayat & jumlah pemakaian WA
 * API pihak ketiga (App\Models\WaApiRequestLog) LINTAS SEMUA company,
 * supaya superadmin/CS bisa menjawab pertanyaan customer ("kenapa pesan
 * saya lewat API tidak terkirim?", "sudah berapa pesan yang keluar?")
 * tanpa perlu login sebagai customer tersebut.
 *
 * Pasangan halaman per-company milik customer sendiri
 * (Chat\WaApiKeyController::history()). Read-only, dan SENGAJA tidak
 * pernah menampilkan token/secret_key API key — cukup device & status.
 *
 * Route dilindungi middleware 'superadmin' (grup route superadmin di
 * routes/web.php).
 */
class WaApiUsageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        [$from, $to] = $this->resolveDateRange($filters);

        $companyIds = null;

        if (! empty($filters['company'])) {
            $companyIds = Company::where('name', 'like', '%'.$filters['company'].'%')->pluck('id');
        }

        $baseQuery = WaApiRequestLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($companyIds !== null, fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        // Satu query GROUP BY per company (bukan query per baris di
        // dalam loop) supaya tidak N+1 walau company-nya banyak.
        $rows = (clone $baseQuery)
            ->selectRaw('company_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent', [WaApiRequestLog::STATUS_SENT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as blocked_quota', [WaApiRequestLog::STATUS_BLOCKED_QUOTA])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as blocked_package', [WaApiRequestLog::STATUS_BLOCKED_PACKAGE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed', [WaApiRequestLog::STATUS_FAILED])
            ->selectRaw('MAX(created_at) as last_request_at')
            ->groupBy('company_id')
            ->orderByDesc('last_request_at')
            ->paginate(25)
            ->withQueryString();

        $companies = Company::whereIn('id', $rows->pluck('company_id'))->get(['id', 'name', 'email', 'phone'])->keyBy('id');

        $totals = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('superadmin.wa-api-usage.index', [
            'rows' => $rows,
            'companies' => $companies,
            'totals' => $totals,
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'statusLabels' => WaApiRequestLog::statusLabels(),
        ]);
    }

    public function show(Request $request, string $company, WaApiUsageService $usage): View
    {
        $company = Company::findOrFail($company);

        $filters = $this->validatedFilters($request, withApiKey: true);
        [$from, $to] = $this->resolveDateRange($filters);

        $apiKeys = WaApiKey::where('company_id', $company->id)
            ->orderBy('created_at')
            ->get(['id', 'company_id', 'device_id', 'device_label', 'status', 'last_used_at', 'created_at']);

        // Jumlah per API key untuk rentang tanggal yang dipilih — satu
        // query GROUP BY untuk semua key company ini.
        $perKey = WaApiRequestLog::where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('wa_api_key_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent', [WaApiRequestLog::STATUS_SENT])
            ->groupBy('wa_api_key_id')
            ->get()
            ->keyBy('wa_api_key_id');

        $logs = WaApiRequestLog::with('apiKey:id,device_id,device_label')
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['api_key'] ?? null, fn ($q, $keyId) => $q->where('wa_api_key_id', $keyId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['recipient'] ?? null, fn ($q, $recipient) => $q->where('recipient', 'like', '%'.preg_replace('/\D+/', '', $recipient).'%'))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $totals = WaApiRequestLog::where('company_id', $company->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('superadmin.wa-api-usage.show', [
            'company' => $company,
            'summary' => $usage->companySummary($company),
            'apiKeys' => $apiKeys,
            'perKey' => $perKey,
            'logs' => $logs,
            'totals' => $totals,
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'statusLabels' => WaApiRequestLog::statusLabels(),
        ]);
    }

    private function validatedFilters(Request $request, bool $withApiKey = false): array
    {
        $rules = [
            'company' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(WaApiRequestLog::statusLabels()))],
            'recipient' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date'],
            'date_to' => array_filter(['nullable', 'date', $request->filled('date_from') ? 'after_or_equal:date_from' : null]),
        ];

        if ($withApiKey) {
            $rules['api_key'] = ['nullable', 'uuid'];
        }

        return $request->validate($rules);
    }

    /**
     * Default ke bulan berjalan kalau tanggal tidak diisi — supaya
     * halaman tidak men-scan seluruh isi tabel log (yang tumbuh terus)
     * setiap kali dibuka.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(array $filters): array
    {
        $from = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();

        $to = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
