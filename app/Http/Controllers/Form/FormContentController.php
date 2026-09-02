<?php

namespace App\Http\Controllers\Form;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormContent;
use App\Models\FormHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * Builder pertanyaan Form Content (level ke-4 drill-down, di bawah Form
 * Header) -- JSON CRUD + halaman shell, mengikuti pola persis App\Http\
 * Controllers\Chat\ChatbotFlowController's step endpoints (bukan pola
 * Blade create/edit terpisah seperti Form Category/Header), karena
 * sifatnya sama: daftar baris yang ditambah/diedit/dihapus dinamis di
 * satu halaman, urut sesuai `position`.
 *
 * `position` di-auto-assign di store() (frontend tidak pernah
 * mengirimnya) -- pelajaran langsung dari perbaikan urutan step
 * Chatbot Flow sesi ini: App\Http\Controllers\Chat\
 * ChatbotFlowController::storeStep().
 */
class FormContentController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * Shell halaman builder-nya -- daftar pertanyaan sendiri dimuat lewat
     * list() via fetch(), sama seperti resources/views/chat/chatbot-flows/index.blade.php.
     */
    public function index(Request $request, string $formHeader): View
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        return view('form.form-content.index', ['header' => $header]);
    }

    public function list(Request $request, string $formHeader): JsonResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        return response()->json(['contents' => $header->contents]);
    }

    public function store(Request $request, string $formHeader): JsonResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $validated = $this->validator($request)->validate();

        $validated['position'] = (FormContent::where('form_header_id', $header->id)->max('position') ?? -1) + 1;

        $content = $header->contents()->create(array_merge($validated, [
            'company_id' => $header->company_id,
            'branch_office_id' => $header->branch_office_id,
            'form_category_id' => $header->form_category_id,
        ]));

        return response()->json(['content' => $content], 201);
    }

    public function update(Request $request, string $formHeader, string $content): JsonResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);
        $contentModel = $header->contents()->whereKey($content)->firstOrFail();

        $validated = $this->validator($request)->validate();
        $contentModel->update($validated);

        return response()->json(['content' => $contentModel->fresh()]);
    }

    public function destroy(Request $request, string $formHeader, string $content): JsonResponse
    {
        $header = $this->ownedHeaderOrFail($request, $formHeader);

        $deleted = $header->contents()->whereKey($content)->delete();

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['status' => 'ok']);
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

    private function validator(Request $request): ValidatorContract
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(FormContent::TYPES)],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:200'],
            'allowed_file_types' => ['nullable', 'string', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $validator->after(function (ValidatorContract $v) use ($request) {
            $type = $request->input('type');

            if (in_array($type, FormContent::CHOICE_TYPES, true) && empty($request->input('options'))) {
                $v->errors()->add('options', 'Pertanyaan pilihan wajib memiliki minimal 1 opsi jawaban.');
            }
        });

        return $validator;
    }
}
