<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormFooter;
use App\Models\FormHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD untuk Form Footer (level ke-5 drill-down, di bawah Form Header)
 * -- blok penutup form publik (teks terima kasih / CTA), 1-banyak per
 * Form Header. Lihat 2026_09_12_090300_create_form_footers_table.php's
 * docblock untuk kenapa tidak terhubung ke Form Content tertentu.
 */
class FormFooterController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request, string $formHeader): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $footers = $header->footers()->latest()->paginate(15)->withQueryString()->onEachSide(1);

        return view('form.form-footer.index', compact('footers', 'header'));
    }

    public function create(Request $request, string $formHeader): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        return view('form.form-footer.create', ['footer' => null, 'header' => $header]);
    }

    public function store(Request $request, string $formHeader): RedirectResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('form.footer.create', $header->id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        FormFooter::create([
            'company_id' => $header->company_id,
            'branch_office_id' => $header->branch_office_id,
            'form_category_id' => $header->form_category_id,
            'form_header_id' => $header->id,
            'name' => $validated['name'],
            'status' => $validated['status'] ?? FormFooter::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.footer.index', $header->id)
            ->with('success', 'Form Footer berhasil ditambahkan.');
    }

    public function edit(Request $request, string $formHeader, string $id): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $footer = $this->findOrFail($header, $id);

        return view('form.form-footer.edit', compact('footer', 'header'));
    }

    public function update(Request $request, string $formHeader, string $id): RedirectResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $footer = $this->findOrFail($header, $id);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('form.footer.edit', [$header->id, $id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $footer->update([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? FormFooter::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.footer.index', $header->id)
            ->with('success', 'Form Footer berhasil diperbarui.');
    }

    public function destroy(Request $request, string $formHeader, string $id): RedirectResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $footer = $this->findOrFail($header, $id);

        $footer->delete();

        return redirect()
            ->route('form.footer.index', $header->id)
            ->with('success', 'Form Footer berhasil dihapus.');
    }

    private function ownedHeaderOrFail(Request $request, string $formHeaderId): FormHeader
    {
        $context = $this->companyContext($request);

        $query = FormHeader::where('company_id', $context->company->id)->where('id', $formHeaderId);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function findOrFail(FormHeader $header, string $id): FormFooter
    {
        return $header->footers()->whereKey($id)->firstOrFail();
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:2000'],
            'status' => ['nullable', 'in:'.FormFooter::STATUS_ACTIVE.','.FormFooter::STATUS_INACTIVE],
        ]);
    }
}
