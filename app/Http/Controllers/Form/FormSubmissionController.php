<?php

namespace App\Http\Controllers\Form;

use App\Helpers\FormImageUploader;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormHeader;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only (+ hapus) daftar jawaban App\Models\FormSubmission yang
 * masuk untuk satu App\Models\FormHeader -- jawaban itu sendiri diisi
 * publik lewat App\Http\Controllers\Form\PublicFormController::store(),
 * controller ini murni sisi admin untuk "siapa saja yang sudah submit,
 * bisa dilihat di mana".
 */
class FormSubmissionController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request, string $formHeader): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $submissions = $header->submissions()
            ->with(['answers' => fn ($q) => $q->with('formContent')->orderBy('created_at')])
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString()
            ->onEachSide(1);

        // Kolom pertanyaan single_line/textarea PERTAMA di form ini
        // dipakai sebagai "ringkasan" per baris submission di tabel index
        // (mis. Nama), murni bantuan visual -- tidak menyimpan apa pun
        // baru, cuma pilih dari urutan pertanyaan yang sudah ada.
        $summaryContent = $header->contents()
            ->whereIn('type', ['single_line', 'textarea'])
            ->orderBy('position')
            ->first();

        return view('form.form-submission.index', compact('header', 'submissions', 'summaryContent'));
    }

    public function show(Request $request, string $formHeader, string $id): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $submission = $header->submissions()
            ->with(['answers' => fn ($q) => $q->with('formContent')])
            ->whereKey($id)
            ->firstOrFail();

        // Urutkan jawaban sesuai urutan pertanyaan di form aslinya
        // (App\Models\FormContent::position), bukan urutan submit --
        // supaya di halaman Detail & saat di-print, urutannya sama
        // persis dengan yang dilihat pengisi form.
        $submission->setRelation(
            'answers',
            $submission->answers->sortBy(fn ($answer) => $answer->formContent->position ?? PHP_INT_MAX)->values()
        );

        return view('form.form-submission.show', compact('header', 'submission'));
    }

    public function destroy(Request $request, string $formHeader, string $id): RedirectResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $submission = $header->submissions()->whereKey($id)->firstOrFail();

        // File jawaban file_upload tersimpan fisik di public/form/submissions
        // -- tidak ikut terhapus otomatis oleh cascadeOnDelete DB (itu cuma
        // baris tabelnya), jadi dibersihkan manual di sini dulu.
        foreach ($submission->answers as $answer) {
            if ($answer->file_path) {
                FormImageUploader::delete($answer->file_path);
            }
        }

        $submission->delete(); // cascade ke form_submission_answers, lihat migration-nya.

        return redirect()
            ->route('form.submission.index', $header->id)
            ->with('success', 'Submission berhasil dihapus.');
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
}
