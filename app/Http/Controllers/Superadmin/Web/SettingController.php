<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\WebSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Singleton settings screen for App\Models\WebSetting (favicon, logo,
 * meta tags, contact info, GTM/GA IDs, Google Maps embed) — same shape
 * as Superadmin\AiModerationSettingController: just edit()/update(), no
 * index/create/destroy, since WebSetting::current() always resolves to
 * the one row that exists (created lazily with all-null defaults on
 * first access).
 *
 * Consumed publicly by fe-konexa via GET /api/frontend/web-setting (see
 * App\Http\Controllers\Api\Frontend\WebSettingController), through
 * App\View\Composers\WebSettingComposer on that side.
 */
class SettingController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'settings';

    public function edit(): View
    {
        $setting = WebSetting::current();

        return view('superadmin.web.setting.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = WebSetting::current();

        $validated = $request->validate([
            'favicon' => ['nullable', 'image', 'max:1024'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'meta_images' => ['nullable', 'image', 'max:4096'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'handphone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'google_tag' => ['nullable', 'string', 'max:50'],
            'google_analytics' => ['nullable', 'string', 'max:50'],
            'gmaps' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($request->hasFile('favicon')) {
            $validated['favicon'] = WebImageUploader::upload($request->file('favicon'), self::IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('logo')) {
            $validated['logo'] = WebImageUploader::upload($request->file('logo'), self::IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('meta_images')) {
            $validated['meta_images'] = WebImageUploader::upload($request->file('meta_images'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebSetting::class,
            $setting->id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if (array_key_exists('favicon', $data) && $model->favicon && $model->favicon !== $data['favicon']) {
                    WebImageUploader::delete($model->favicon);
                }

                if (array_key_exists('logo', $data) && $model->logo && $model->logo !== $data['logo']) {
                    WebImageUploader::delete($model->logo);
                }

                if (array_key_exists('meta_images', $data) && $model->meta_images && $model->meta_images !== $data['meta_images']) {
                    WebImageUploader::delete($model->meta_images);
                }

                return $data;
            }
        );

        return redirect()
            ->route('web.setting.edit')
            ->with('success', 'Pengaturan web berhasil disimpan.');
    }
}
