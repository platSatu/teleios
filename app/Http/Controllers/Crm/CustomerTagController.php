<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaCustomer;
use App\Models\WaCustomerTag;
use App\Services\Crm\CustomerAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The App\Models\WaCustomerTag catalog (create/rename/delete a tag) and
 * attaching/detaching a tag on a specific App\Models\WaCustomer — CRM
 * Roadmap Fase 4's "tagging" half of "segmen dinamis". The catalog is
 * managed from the "Segmentasi" page's tag panel
 * (resources/views/chat/segments/index.blade.php); attach/detach is
 * done inline from the Customer 360 page
 * (resources/views/crm/customers/show.blade.php).
 *
 * attach() is the one write path that can fire a 'tag_added'
 * App\Models\WaCustomerAutomationRule (via
 * App\Services\Crm\CustomerAutomationService::fireTagAdded()) — every
 * other way a tag could theoretically end up on a customer is
 * deliberately not exposed, so that trigger never gets silently
 * bypassed.
 */
class CustomerTagController extends Controller
{
    use ResolvesCompanyContext;

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        WaCustomerTag::firstOrCreate(
            ['company_id' => $context->company->id, 'name' => $validated['name']],
            ['color' => $validated['color'] ?? 'secondary']
        );

        return back()->with('success', 'Tag berhasil dibuat.');
    }

    public function update(Request $request, string $tag): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = WaCustomerTag::where('company_id', $context->company->id)
            ->where('id', $tag)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $record->update($validated);

        return back()->with('success', 'Tag berhasil diperbarui.');
    }

    public function destroy(Request $request, string $tag): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = WaCustomerTag::where('company_id', $context->company->id)
            ->where('id', $tag)
            ->firstOrFail();

        $record->delete();

        return back()->with('success', 'Tag berhasil dihapus.');
    }

    public function attach(Request $request, string $customer, CustomerAutomationService $automation): RedirectResponse
    {
        $context = $this->companyContext($request);

        $customerRecord = WaCustomer::where('company_id', $context->company->id)
            ->where('id', $customer)
            ->firstOrFail();

        $validated = $request->validate([
            'wa_customer_tag_id' => ['required', 'uuid'],
        ]);

        $tag = WaCustomerTag::where('company_id', $context->company->id)
            ->where('id', $validated['wa_customer_tag_id'])
            ->firstOrFail();

        if (! $customerRecord->tags()->where('wa_customer_tags.id', $tag->id)->exists()) {
            $customerRecord->tags()->attach($tag->id, [
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);

            $automation->fireTagAdded($customerRecord, $tag);
        }

        return back()->with('success', 'Tag berhasil ditambahkan.');
    }

    public function detach(Request $request, string $customer, string $tag): RedirectResponse
    {
        $context = $this->companyContext($request);

        $customerRecord = WaCustomer::where('company_id', $context->company->id)
            ->where('id', $customer)
            ->firstOrFail();

        $customerRecord->tags()->detach($tag);

        return back()->with('success', 'Tag berhasil dihapus dari pelanggan.');
    }
}
