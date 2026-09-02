<?php

namespace App\Http\Controllers\Form;

use App\Helpers\FormImageUploader;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\FormCategory;
use App\Models\FormHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CRUD untuk Form Header (level ke-3 drill-down, di bawah Form
 * Category) -- satu baris = satu form publik yang bisa diisi lewat
 * app.konexa.id/{slug}, lihat App\Http\Controllers\Form\
 * PublicFormController.
 *
 * `slug` dibuat OTOMATIS dari `name` sekali saat create (generateUniqueSlug())
 * dan TIDAK PERNAH berubah lagi setelahnya walau `name` diedit -- URL
 * publik yang sudah dibagikan/ditempel di brosur tidak boleh mendadak
 * putus cuma karena admin ganti judul form.
 */
class FormHeaderController extends Controller
{
    use ResolvesCompanyContext;

    private const IMAGE_SUBDIRECTORY = 'background';

    /**
     * Segmen top-level yang sudah dipakai rute sistem -- slug form TIDAK
     * boleh sama dengan ini, karena app.konexa.id/{slug} didaftarkan
     * sebagai rute TOP-LEVEL juga (lihat routes/web.php, sengaja
     * didaftarkan PALING BAWAH supaya tidak pernah menang lawan rute
     * spesifik manapun -- blocklist ini cuma jaga-jaga tambahan).
     */
    public const RESERVED_SLUGS = [
        'dashboard', 'login', 'logout', 'register', 'password', 'forgot-password',
        'reset-password', 'confirm-password', 'verify-email', 'email', 'storage',
        'dokumentasi', 'api', 'up', 'form', 'sanctum', 'broadcasting', 'livewire',
    ];

    public function index(Request $request, string $formCategory): View
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);

        $headers = $category->headers()
            ->withCount(['contents', 'submissions'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->onEachSide(1);

        return view('form.form-header.index', compact('headers', 'category'));
    }

    public function create(Request $request, string $formCategory): View
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);

        return view('form.form-header.create', ['header' => null, 'category' => $category]);
    }

    public function store(Request $request, string $formCategory): RedirectResponse
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('form.header.create', $category->id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        FormHeader::create([
            'company_id' => $category->company_id,
            'branch_office_id' => $category->branch_office_id,
            'form_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => self::generateUniqueSlug($validated['name']),
            'background' => $request->hasFile('background')
                ? FormImageUploader::upload($request->file('background'), self::IMAGE_SUBDIRECTORY)
                : null,
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? FormHeader::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.header.index', $category->id)
            ->with('success', 'Form Header berhasil ditambahkan.');
    }

    public function edit(Request $request, string $formCategory, string $id): View
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);
        $header = $this->findOrFail($category, $id);

        return view('form.form-header.edit', compact('header', 'category'));
    }

    public function update(Request $request, string $formCategory, string $id): RedirectResponse
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);
        $header = $this->findOrFail($category, $id);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('form.header.edit', [$category->id, $id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $newBackground = $header->background;

        if ($request->hasFile('background')) {
            $newBackground = FormImageUploader::upload($request->file('background'), self::IMAGE_SUBDIRECTORY);
        } elseif ($request->boolean('remove_background')) {
            $newBackground = null;
        }

        if ($newBackground !== $header->background) {
            FormImageUploader::delete($header->background);
        }

        $header->update([
            'name' => $validated['name'],
            // slug SENGAJA tidak diubah -- lihat docblock kelas ini.
            'background' => $newBackground,
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'] ?? FormHeader::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('form.header.index', $category->id)
            ->with('success', 'Form Header berhasil diperbarui.');
    }

    public function destroy(Request $request, string $formCategory, string $id): RedirectResponse
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);
        $header = $this->findOrFail($category, $id);

        FormImageUploader::delete($header->background);
        $header->delete();

        return redirect()
            ->route('form.header.index', $category->id)
            ->with('success', 'Form Header berhasil dihapus.');
    }

    /**
     * Ganti slug (URL publik + isi QR Code) form ini dengan yang baru --
     * dipakai lewat tombol "Regenerate URL & QR" di index. URL/QR LAMA
     * yang sudah dibagikan/dicetak otomatis TIDAK BERLAKU LAGI setelah
     * ini (404 di App\Http\Controllers\Form\PublicFormController::show()),
     * makanya di-confirm dulu di sisi UI sebelum request ini dikirim.
     * Beda dari slug generation di store() -- di sini SENGAJA selalu
     * dipaksa beda dari slug saat ini walau nama form tidak berubah,
     * supaya tombol "Regenerate" selalu terasa berefek.
     */
    public function regenerateSlug(Request $request, string $formCategory, string $id): RedirectResponse
    {
        $category = $this->ownedCategoryOrFail($request, $formCategory);
        $header = $this->findOrFail($category, $id);

        $newSlug = self::generateUniqueSlug($header->name);

        if ($newSlug === $header->slug) {
            // generateUniqueSlug() balik persis slug lama (kasus jarang --
            // cuma bisa kejadian kalau slug lama itu2 juga uniknya masih
            // valid dan tidak reserved); tambahkan suffix acak pendek
            // supaya benar2 berubah.
            $newSlug = $newSlug.'-'.strtolower(Str::random(4));
        }

        $header->update(['slug' => $newSlug]);

        return redirect()
            ->route('form.header.index', $category->id)
            ->with('success', 'URL & QR Code baru berhasil dibuat: '.$newSlug.'. URL lama sudah tidak berlaku.');
    }

    /**
     * Dipakai di sini (create) DAN App\Http\Controllers\Form\
     * FormCategoryController::duplicate() (bikin slug baru untuk hasil
     * copy) -- satu tempat generator supaya aturan uniknya (global,
     * lintas company) tidak terduplikasi.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        $slug = $base;
        $suffix = 1;

        while (in_array($slug, self::RESERVED_SLUGS, true) || FormHeader::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    private function ownedCategoryOrFail(Request $request, string $formCategoryId): FormCategory
    {
        $context = $this->companyContext($request);

        $query = FormCategory::where('company_id', $context->company->id)->where('id', $formCategoryId);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function findOrFail(FormCategory $category, string $id): FormHeader
    {
        return $category->headers()->whereKey($id)->firstOrFail();
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_background' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:'.FormHeader::STATUS_ACTIVE.','.FormHeader::STATUS_INACTIVE],
        ]);
    }
}
