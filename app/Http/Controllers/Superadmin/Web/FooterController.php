<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebFooter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for footer link/block entries (App\Models\WebFooter).
 * Same shape as Superadmin\Web\HeaderController: CrudAdmin for data
 * access, background_image through App\Helpers\WebImageUploader, old
 * file cleaned up on replace/delete.
 */
class FooterController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'footers';

    public function index(Request $request): View
    {
        $footers = CrudAdmin::getAll(
            modelClass: WebFooter::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'link'],
        );

        return view('superadmin.web.footers.index', compact('footers'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.footers.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('background_image')) {
            $validated['background_image'] = WebImageUploader::upload($request->file('background_image'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::store(WebFooter::class, $validated);

        return redirect()
            ->route('web.footers.index')
            ->with('success', 'Footer berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $footer = CrudAdmin::find(WebFooter::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.footers.edit', compact('footer', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('background_image')) {
            $validated['background_image'] = WebImageUploader::upload($request->file('background_image'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebFooter::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if (array_key_exists('background_image', $data) && $model->background_image && $model->background_image !== $data['background_image']) {
                    WebImageUploader::delete($model->background_image);
                }

                return $data;
            }
        );

        return redirect()
            ->route('web.footers.index')
            ->with('success', 'Footer berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebFooter::class, $id, function ($model) {
            WebImageUploader::delete($model->background_image);
        });

        return redirect()
            ->route('web.footers.index')
            ->with('success', 'Footer berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'background_image' => ['nullable', 'image', 'max:4096'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'column_width' => ['required', 'in:col-md-3,col-md-4'],
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'link' => ['required', 'string', 'max:255'],
            'target_blank' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $validated['target_blank'] = $request->boolean('target_blank');

        return $validated;
    }
}
