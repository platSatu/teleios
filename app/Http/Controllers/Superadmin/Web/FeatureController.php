<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for highlighted product/service features
 * (App\Models\WebFeature) — flat list, no category, one image per row.
 * Same shape as Superadmin\Web\FaqController, plus image upload/cleanup
 * through App\Helpers\WebImageUploader (same pattern as
 * Superadmin\Web\ArticleController's `images` handling, minus slug/meta
 * tags — WebFeature doesn't have those).
 *
 * Rows already exist from database/seeders/WebFeatureSeeder.php with
 * `images` left null — this CRUD is what lets a superadmin upload the
 * actual image per feature afterwards, per the user's request.
 */
class FeatureController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'features';

    public function index(Request $request): View
    {
        $features = CrudAdmin::getAll(
            modelClass: WebFeature::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.web.features.index', compact('features'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.features.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::store(WebFeature::class, $validated);

        return redirect()
            ->route('web.features.index')
            ->with('success', 'Fitur berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $feature = CrudAdmin::find(WebFeature::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.features.edit', compact('feature', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebFeature::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if (array_key_exists('images', $data) && $model->images && $model->images !== $data['images']) {
                    WebImageUploader::delete($model->images);
                }

                return $data;
            }
        );

        return redirect()
            ->route('web.features.index')
            ->with('success', 'Fitur berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebFeature::class, $id, function ($model) {
            WebImageUploader::delete($model->images);
        });

        return redirect()
            ->route('web.features.index')
            ->with('success', 'Fitur berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'images' => ['nullable', 'image', 'max:4096'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
