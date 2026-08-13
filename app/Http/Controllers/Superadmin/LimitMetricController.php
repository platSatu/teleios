<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\LimitMetric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for the LimitMetric catalog — see the migration's
 * docblock for why `category_application_id` is nullable (a global
 * metric key any product's packages may reuse) and what `metric_type`
 * means. All data access goes through CrudAdmin, same as every other
 * superadmin resource.
 */
class LimitMetricController extends Controller
{
    public function index(Request $request): View
    {
        $limitMetrics = CrudAdmin::getAll(
            modelClass: LimitMetric::class,
            relations: ['categoryApplication'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['key', 'name', 'description'],
        );

        return view('superadmin.limit-metric.index', compact('limitMetrics'));
    }

    public function create(): View
    {
        $categoryApplications = $this->formOptions();

        return view('superadmin.limit-metric.create', compact('categoryApplications'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(LimitMetric::class, $validated);

        return redirect()
            ->route('limit-metric.index')
            ->with('success', 'Limit metric berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $limitMetric = CrudAdmin::find(LimitMetric::class, $id);
        $categoryApplications = $this->formOptions();

        return view('superadmin.limit-metric.edit', compact('limitMetric', 'categoryApplications'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(LimitMetric::class, $id, $validated);

        return redirect()
            ->route('limit-metric.index')
            ->with('success', 'Limit metric berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(LimitMetric::class, $id);

        return redirect()
            ->route('limit-metric.index')
            ->with('success', 'Limit metric berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category_application_id' => ['nullable', 'uuid', 'exists:category_applications,id'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'metric_type' => ['required', 'in:consumable,stock'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function formOptions()
    {
        return CategoryApplication::orderBy('name')->get(['id', 'name']);
    }
}
