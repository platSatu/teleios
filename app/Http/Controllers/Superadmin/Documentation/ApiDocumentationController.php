<?php

namespace App\Http\Controllers\Superadmin\Documentation;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\ApiDocumentation;
use App\Models\CategoryDocumentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Superadmin CRUD for individual API documentation articles (one per
 * endpoint — title, HTTP method, path, description, request/response
 * examples), rendered publicly at GET /dokumentasi (no login required —
 * see PublicDocumentationController). Same CrudAdmin-backed shape as
 * Superadmin\Documentation\CategoryDocumentationController.
 */
class ApiDocumentationController extends Controller
{
    public function index(Request $request): View
    {
        $articles = CrudAdmin::getAll(
            modelClass: ApiDocumentation::class,
            relations: ['categoryDocumentation'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['title', 'endpoint', 'description'],
        );

        return view('superadmin.wa-api-dokumentasi.articles.index', compact('articles'));
    }

    public function create(): View
    {
        $categories = CategoryDocumentation::orderBy('sort_order')->orderBy('name')->get();

        return view('superadmin.wa-api-dokumentasi.articles.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['title']);

        CrudAdmin::store(ApiDocumentation::class, $validated);

        return redirect()
            ->route('wa-api-dokumentasi.articles.index')
            ->with('success', 'Artikel dokumentasi berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $article = CrudAdmin::find(ApiDocumentation::class, $id, relations: ['categoryDocumentation']);

        return view('superadmin.wa-api-dokumentasi.articles.show', compact('article'));
    }

    public function edit(string $id): View
    {
        $article = CrudAdmin::find(ApiDocumentation::class, $id);
        $categories = CategoryDocumentation::orderBy('sort_order')->orderBy('name')->get();

        return view('superadmin.wa-api-dokumentasi.articles.edit', compact('article', 'categories'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(ApiDocumentation::class, $id, $validated, function ($model, $data) {
            if ($data['title'] !== $model->title) {
                $data['slug'] = $this->uniqueSlug($data['title'], $model->id);
            }

            return $data;
        });

        return redirect()
            ->route('wa-api-dokumentasi.articles.index')
            ->with('success', 'Artikel dokumentasi berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(ApiDocumentation::class, $id);

        return redirect()
            ->route('wa-api-dokumentasi.articles.index')
            ->with('success', 'Artikel dokumentasi berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category_documentation_id' => ['required', 'uuid', 'exists:category_documentations,id'],
            'title' => ['required', 'string', 'max:255'],
            'method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'endpoint' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'request_example' => ['nullable', 'string'],
            'response_example' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        $i = 1;

        while (
            ApiDocumentation::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
