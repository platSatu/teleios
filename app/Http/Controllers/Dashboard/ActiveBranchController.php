<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Services\Company\ActiveBranchSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pemilih branch di header -- mengganti branch yang sedang "dibuka"
 * (menentukan menu & paket yang berlaku). Hanya branch yang memang boleh
 * dibuka user ini yang diterima (lihat ActiveBranchSelection::select());
 * member yang terkunci ke satu branch tidak bisa pindah.
 */
class ActiveBranchController extends Controller
{
    use ResolvesCompanyContext;

    public function update(Request $request, ActiveBranchSelection $selection): RedirectResponse
    {
        $validated = $request->validate([
            'branch_office_id' => ['required', 'uuid'],
        ]);

        if (! $selection->select($this->companyContext($request), $validated['branch_office_id'])) {
            return back()->with('error', 'Branch tidak ditemukan atau Anda tidak memiliki akses ke branch tersebut.');
        }

        // Kembali ke dashboard, bukan back(): halaman sebelumnya bisa saja
        // menu yang tidak termasuk paket branch baru.
        return redirect()->route('dashboard')->with('success', 'Branch aktif berhasil diganti.');
    }
}
