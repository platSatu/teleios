<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Titik masuk paling atas drill-down fitur Form: Branch -> Form Category
 * -> Form Header -> Form Content -> Form Footer -> Form Setting. Sama
 * persis pola & alasannya dengan App\Http\Controllers\Jadwal\
 * JadwalBranchController -- read-only, cuma pintu masuk pilih branch
 * buat bikin Form Category baru lewat tombol "+ Add Form Category" di
 * tiap baris.
 */
class FormBranchController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = BranchOffice::where('company_id', $company->id)
            ->withCount(['formCategories as form_category_count']);

        if ($context->isLockedToBranch()) {
            $query->where('id', $context->branchOffice?->id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $branches = $query->orderBy('name')->paginate(15)->withQueryString()->onEachSide(1);

        return view('form.form-branch.index', compact('branches'));
    }
}
