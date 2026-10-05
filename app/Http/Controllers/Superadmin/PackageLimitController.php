<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Models\LimitMetric;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin CRUD assigning a numeric ceiling (App\Models\PackageLimit)
 * to one App\Models\LimitMetric on one App\Models\Package — e.g. Package
 * "Paket A" + metric "broadcast_send" -> max_value 10000. A package with
 * no row here for a given metric is unlimited for it — see
 * App\Services\PackageLimitService::limitFor().
 */
class PackageLimitController extends Controller
{
    public function index(Request $request): View
    {
        $packageLimits = CrudAdmin::getAll(
            modelClass: PackageLimit::class,
            relations: ['package.categoryApplication', 'limitMetric'],
            search: $request->string('search')->value() ?: null,
            searchFields: [],
        );

        return view('superadmin.package-limit.index', compact('packageLimits'));
    }

    /** ?copy={id}: form Tambah diisi dari limit yang sudah ada (tombol Copy di index). */
    public function create(Request $request): View
    {
        [$packages, $limitMetrics] = $this->formOptions();
        $copyFrom = $request->filled('copy') ? PackageLimit::find($request->query('copy')) : null;

        return view('superadmin.package-limit.create', compact('packages', 'limitMetrics', 'copyFrom'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(PackageLimit::class, $validated);

        return redirect()
            ->route('package-limit.index')
            ->with('success', 'Package limit berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $packageLimit = CrudAdmin::find(PackageLimit::class, $id);
        [$packages, $limitMetrics] = $this->formOptions();

        return view('superadmin.package-limit.edit', compact('packageLimit', 'packages', 'limitMetrics'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request, $id);

        CrudAdmin::update(PackageLimit::class, $id, $validated);

        return redirect()
            ->route('package-limit.index')
            ->with('success', 'Package limit berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(PackageLimit::class, $id);

        return redirect()
            ->route('package-limit.index')
            ->with('success', 'Package limit berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'package_id' => ['required', 'uuid', 'exists:packages,id'],
            // Satu package hanya boleh punya 1 limit per metric (unique index di DB).
            'limit_metric_id' => [
                'required', 'uuid', 'exists:limit_metrics,id',
                Rule::unique('package_limits')->where('package_id', $request->input('package_id'))->ignore($ignoreId),
            ],
            'max_value' => ['required', 'integer', 'min:1'],
        ], [
            'limit_metric_id.unique' => 'Package ini sudah punya limit untuk metric tersebut. Pilih package atau metric lain, atau edit limit yang sudah ada.',
        ]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function formOptions(): array
    {
        return [
            Package::orderBy('name')->get(['id', 'name']),
            LimitMetric::where('status', 'active')->orderBy('name')->get(['id', 'name', 'unit']),
        ];
    }
}
