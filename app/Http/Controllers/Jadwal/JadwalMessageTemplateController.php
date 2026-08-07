<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalMessageTemplate;
use App\Services\Jadwal\JadwalMessageTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * "Ajarin format untuk konfirmasinya menggunakan template whatsapp" —
 * lets a company edit their own wording for the highest-frequency
 * Jadwal WA messages (H-1 reminders + reply acknowledgements), no
 * superadmin approval needed since these fire automatically every day —
 * see App\Services\Jadwal\JadwalMessageTemplateService's docblock for
 * why this is deliberately separate from Chat's WA Template builder.
 */
class JadwalMessageTemplateController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected JadwalMessageTemplateService $templates)
    {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $companyId = $context->company->id;

        $items = collect(JadwalMessageTemplateService::DEFINITIONS)->map(function (array $def, string $key) use ($companyId) {
            return [
                'key' => $key,
                'label' => $def['label'],
                'placeholders' => $def['placeholders'],
                'default' => $def['default'],
                'body' => $this->templates->effectiveBody($companyId, $key),
                'is_customized' => $this->templates->isCustomized($companyId, $key),
            ];
        })->values();

        return view('jadwal.pengaturan-pesan.index', ['items' => $items]);
    }

    public function update(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $companyId = $context->company->id;

        $keys = array_keys(JadwalMessageTemplateService::DEFINITIONS);

        $validator = Validator::make($request->all(), [
            'body' => ['required', 'array'],
            'body.*' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengaturan-pesan.index')
                ->withErrors($validator);
        }

        $bodies = $validator->validated()['body'];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $bodies)) {
                continue;
            }

            $value = trim((string) $bodies[$key]);
            $default = trim(JadwalMessageTemplateService::DEFINITIONS[$key]['default']);

            // Storing an exact-match-to-default as NULL keeps the row
            // meaning "using the default" rather than "customized to
            // text that happens to equal the default" — irrelevant
            // functionally (render() falls back the same either way)
            // but keeps isCustomized()'s "Reset ke Default" button
            // accurate.
            JadwalMessageTemplate::updateOrCreate(
                ['company_id' => $companyId, 'message_key' => $key],
                ['body' => ($value === '' || $value === $default) ? null : $value]
            );
        }

        return redirect()
            ->route('jadwal.pengaturan-pesan.index')
            ->with('success', 'Redaksi pesan berhasil disimpan.');
    }

    public function reset(Request $request, string $key): RedirectResponse
    {
        $context = $this->companyContext($request);

        if (! array_key_exists($key, JadwalMessageTemplateService::DEFINITIONS)) {
            abort(404);
        }

        JadwalMessageTemplate::where('company_id', $context->company->id)
            ->where('message_key', $key)
            ->delete();

        return redirect()
            ->route('jadwal.pengaturan-pesan.index')
            ->with('success', 'Redaksi pesan dikembalikan ke default.');
    }
}
