<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TagihanPenerima;
use App\Models\TransactionStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Laporan" -- daftar & histori tagihan_penerima LINTAS Tagihan
 * (berbeda dari TagihanController::show() yang cuma menampilkan
 * penerima milik SATU Tagihan). Ini yang dimaksud user waktu bilang
 * "yang penting adalah laporan dan history nya" soal fitur denda.
 * Read-mostly -- satu-satunya aksi tulis di sini adalah cancel() dan
 * regenerateToken(), keduanya tidak pernah mengubah status ke 'lunas'
 * (itu murni hak App\Http\Controllers\Tagihan\
 * TagihanDuitkuCallbackController lewat webhook Duitku).
 */
class TagihanPenerimaController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = TagihanPenerima::where('company_id', $company->id)
            ->with(['pelanggan:id,name,phone_number', 'tagihan:id,name,due_date,tagihan_category_id', 'tagihan.category:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('pelanggan', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $penerimaList = $query->latest()->paginate(20)->withQueryString()->onEachSide(1);

        return view('tagihan.penerima.index', compact('penerimaList'));
    }

    public function show(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $penerima = $this->findOrFail($context, $id);
        $penerima->load(['pelanggan', 'tagihan.category', 'branchOffice:id,name,slug', 'paymentTransactions', 'reminderLogs.rule']);

        return view('tagihan.penerima.show', compact('penerima'));
    }

    /**
     * Batalkan invoice ini (status -> dibatalkan) tanpa menghapus baris
     * -- histori tetap ada. Hanya boleh untuk yang masih belum_bayar;
     * yang lunas tidak pernah bisa dibatalkan dari sini.
     */
    public function cancel(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $penerima = $this->findOrFail($context, $id);

        if ($penerima->status !== TagihanPenerima::STATUS_BELUM_BAYAR) {
            return redirect()
                ->route('tagihan.laporan.show', $penerima->id)
                ->with('error', 'Hanya invoice berstatus "Belum Bayar" yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($penerima) {
            $oldStatus = $penerima->status;

            $penerima->update(['status' => TagihanPenerima::STATUS_DIBATALKAN]);

            TransactionStatusHistory::create([
                'entity_type' => TagihanPenerima::class,
                'entity_id' => $penerima->id,
                'old_status' => $oldStatus,
                'new_status' => TagihanPenerima::STATUS_DIBATALKAN,
                'changed_by' => Auth::id(),
            ]);

            AuditLog::create([
                'actor_type' => 'USER',
                'actor_id' => Auth::id(),
                'action' => 'CANCEL_TAGIHAN_PENERIMA',
                'entity_type' => 'TagihanPenerima',
                'entity_id' => $penerima->id,
                'old_value' => ['status' => $oldStatus],
                'new_value' => ['status' => TagihanPenerima::STATUS_DIBATALKAN],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('tagihan.laporan.show', $penerima->id)
            ->with('success', 'Invoice berhasil dibatalkan.');
    }

    /**
     * Ganti public_token -- kalau link lama sudah ter-share ke orang
     * yang salah, link lama langsung berhenti berfungsi (halaman
     * publik lookup berdasarkan token persis, lihat
     * App\Http\Controllers\Tagihan\Public\TagihanPublicController).
     */
    public function regenerateToken(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $penerima = $this->findOrFail($context, $id);

        $penerima->update(['public_token' => (string) Str::uuid()]);

        return redirect()
            ->route('tagihan.laporan.show', $penerima->id)
            ->with('success', 'Link pembayaran berhasil diperbarui. Link lama sudah tidak berlaku.');
    }

    private function findOrFail($context, string $id): TagihanPenerima
    {
        $query = TagihanPenerima::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }
}
