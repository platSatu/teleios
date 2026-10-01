<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Lazada\Concerns\ScopesToActiveBranch;
use App\Models\MarketplaceOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Marketplace > Lazada > Pesanan: daftar pesanan hasil sinkron (hanya baca)
 * dari toko-toko Lazada di branch yang sedang dibuka.
 */
class LazadaOrderController extends Controller
{
    use ScopesToActiveBranch;

    public function index(Request $request): View
    {
        $branch = $this->activeBranchOrFail($request);
        $shops = $this->lazadaShopsQuery($branch)->orderBy('name')->get(['id', 'name']);

        $filters = $request->validate([
            'shop' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:64'],
        ]);

        $orders = MarketplaceOrder::query()
            // Per branch (bukan per toko aktif) -- pesanan toko yang sudah
            // diputus tetap tampil sebagai riwayat.
            ->where('company_id', $branch->company_id)
            ->where('branch_office_id', $branch->id)
            ->when($filters['shop'] ?? null, fn ($q, $shopId) => $q->where('marketplace_shop_id', $shopId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('order_number', 'like', addcslashes($search, '%_\\').'%')
                ->orWhere('external_order_id', 'like', addcslashes($search, '%_\\').'%')))
            ->with('shop:id,name')
            ->orderByDesc('ordered_at')
            ->paginate(20)
            ->withQueryString()
            ->onEachSide(1);

        return view('marketplace.lazada.orders.index', [
            'branch' => $branch,
            'shops' => $shops,
            'orders' => $orders,
            'filters' => $filters,
            'statusLabels' => MarketplaceOrder::STATUS_LABELS,
        ]);
    }
}
