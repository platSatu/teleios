<?php

namespace App\Http\Controllers\Chat\Widget;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\ChatWidget;
use App\Services\Company\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan Live Chat Widget (menu Chat > Live Chat Widget). Pola
 * akses sama dengan Chat\AiBotController: owner melihat semua cabang,
 * staff hanya cabangnya sendiri.
 */
class ChatWidgetController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('chat.widgets.index', [
            'widgets' => $this->scoped($context)->with('branchOffice')->withCount('conversations')->latest()->get(),
            'branchOffices' => $context->isOwner
                ? BranchOffice::where('company_id', $context->company->id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'isOwner' => $context->isOwner,
            'lockedBranchOffice' => $context->branchOffice,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $validated = $this->validated($request, $context, 'newWidget');

        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        ChatWidget::create($validated + ['company_id' => $context->company->id]);

        return redirect()->route('chat.widgets.index')->with('success', 'Widget dibuat. Salin kode pemasangannya ke website Anda.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $widget = $this->scoped($context)->findOrFail($id);
        $validated = $this->validated($request, $context, 'editWidget'.$widget->id);

        if ($validated instanceof RedirectResponse) {
            return $validated;
        }

        $widget->update($validated);

        return redirect()->route('chat.widgets.index')->with('success', 'Widget diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->scoped($this->companyContext($request))->findOrFail($id)->delete();

        return redirect()->route('chat.widgets.index')->with('success', 'Widget dihapus beserta riwayat chatnya.');
    }

    /** Key lama langsung tidak berlaku (mis. kode bocor / dipasang orang lain). */
    public function regenerateKey(Request $request, string $id): RedirectResponse
    {
        $widget = $this->scoped($this->companyContext($request))->findOrFail($id);
        $widget->update(['public_key' => ChatWidget::newPublicKey()]);

        return redirect()->route('chat.widgets.index')->with('success', 'Key widget sudah diganti. Pasang ulang kode baru di website Anda.');
    }

    private function scoped(CompanyContext $context): Builder
    {
        return ChatWidget::where('company_id', $context->company->id)
            ->when(! $context->isOwner, fn (Builder $query) => $query->where('branch_office_id', $context->branchOffice?->id));
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function validated(Request $request, CompanyContext $context, string $errorBag): array|RedirectResponse
    {
        // "tokosaya.com, https://www.toko2.id/halaman" -> ["tokosaya.com", "toko2.id"]
        $domains = collect(preg_split('/[\s,]+/', (string) $request->input('allowed_domains')))
            ->map(fn ($domain) => strtolower((string) preg_replace(['#^[a-z]+://#i', '#^www\.#i', '#[/:?].*$#'], '', trim($domain))))
            ->filter()
            ->unique()
            ->values();

        $validator = Validator::make($request->all() + ['domains' => $domains->all()], [
            'name' => ['required', 'string', 'max:100'],
            'domains' => ['required', 'array', 'min:1', 'max:10'],
            'domains.*' => ['regex:/^(localhost|([a-z0-9-]+\.)+[a-z]{2,})$/'],
            'branch_office_id' => ['nullable', 'uuid', Rule::exists('branch_offices', 'id')->where('company_id', $context->company->id)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', 'in:left,right'],
            'title' => ['required', 'string', 'max:60'],
            'greeting' => ['required', 'string', 'max:300'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'domains.required' => 'Isi minimal 1 domain website, mis. tokosaya.com.',
            'domains.*.regex' => 'Format domain tidak valid. Contoh: tokosaya.com',
        ]);

        if ($validator->fails()) {
            return redirect()->route('chat.widgets.index')->withErrors($validator, $errorBag)->withInput();
        }

        $data = $validator->validated();

        return [
            'name' => $data['name'],
            'allowed_domains' => $domains->all(),
            'branch_office_id' => $context->isOwner ? ($data['branch_office_id'] ?? null) : $context->branchOffice?->id,
            'settings' => [
                'color' => $data['color'],
                'position' => $data['position'],
                'title' => $data['title'],
                'greeting' => $data['greeting'],
                'require_contact' => $request->boolean('require_contact'),
            ],
            'ai_enabled' => $request->boolean('ai_enabled'),
            'status' => $data['status'],
        ];
    }
}
