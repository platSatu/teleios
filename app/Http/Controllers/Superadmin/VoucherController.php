<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin CRUD for vouchers. All data access goes through CrudAdmin
 * (app/Helpers/CrudAdmin.php), which enforces the superadmin-only guard
 * and writes every store/update/delete to the audit_logs table.
 *
 * Additionally, every store/update/destroy writes a matching row to
 * voucher_histories (App\Models\VoucherHistory) — a voucher-specific
 * before/after trail distinct from the generic audit_logs entry, for
 * the "History Vouchers" page in the sidebar. audit_logs still gets
 * its entry too via CrudAdmin; this is a second, narrower log purely
 * about vouchers.
 */
class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $vouchers = CrudAdmin::getAll(
            modelClass: Voucher::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['kode_voucher'],
        );

        return view('superadmin.voucher.index', compact('vouchers'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.voucher.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $voucher = CrudAdmin::store(Voucher::class, $validated);

        $this->logHistory($voucher->id, $voucher->user_id, 'CREATE', null, $voucher->toArray());

        return redirect()
            ->route('voucher.index')
            ->with('success', 'Voucher berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $voucher = CrudAdmin::find(Voucher::class, $id, relations: ['user']);

        $histories = VoucherHistory::where('voucher_id', $id)
            ->with('actionBy')
            ->latest()
            ->get();

        return view('superadmin.voucher.show', compact('voucher', 'histories'));
    }

    public function edit(string $id): View
    {
        $voucher = CrudAdmin::find(Voucher::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.voucher.edit', compact('voucher', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        $before = null;

        $voucher = CrudAdmin::update(
            Voucher::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) use (&$before) {
                $before = $model->toArray();

                return $data;
            }
        );

        $this->logHistory($voucher->id, $voucher->user_id, 'UPDATE', $before, $voucher->toArray());

        return redirect()
            ->route('voucher.index')
            ->with('success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $before = null;

        CrudAdmin::delete(
            Voucher::class,
            $id,
            beforeDelete: function ($model) use (&$before) {
                $before = $model->toArray();
            }
        );

        $this->logHistory($id, $before['user_id'] ?? null, 'DELETE', $before, null);

        return redirect()
            ->route('voucher.index')
            ->with('success', 'Voucher berhasil dihapus.');
    }

    /**
     * Writes one row to voucher_histories. Best-effort in spirit (same
     * as CrudAdmin::writeAudit) but not wrapped in try/catch here since
     * CrudAdmin's own store/update/delete calls above already ran
     * inside their own DB transaction and committed — a failure here
     * would just be a plain 500, which is acceptable for now given
     * this table has no other writer to fall back on.
     */
    private function logHistory(string $voucherId, ?string $voucherUserId, string $action, ?array $old, ?array $new): void
    {
        VoucherHistory::create([
            'voucher_id' => $voucherId,
            'user_id' => $voucherUserId,
            'action_by' => Auth::id(),
            'action' => $action,
            'old_data' => $old,
            'new_data' => $new,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'kode_voucher' => [
                'required',
                'string',
                'max:64',
                // ->ignore(null) on create is a no-op, exactly what we
                // want — only excludes the current row when editing.
                Rule::unique('vouchers', 'kode_voucher')->ignore($request->route('id')),
            ],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            // 'pending' included alongside active/inactive: package
            // checkout (Dashboard\PackageCheckoutController) generates
            // vouchers in that state before they're redeemed (Dashboard\
            // VoucherRedeemController), and superadmin can still view/
            // edit those same rows here.
            'status' => ['required', 'in:active,inactive,pending'],
        ]);
    }
}
