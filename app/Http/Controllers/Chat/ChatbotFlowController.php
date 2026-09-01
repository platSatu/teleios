<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaChatbotFlow;
use App\Models\WaChatbotFlowStep;
use App\Services\Chat\DeviceDirectory;
use App\Services\Company\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * JSON CRUD for Fitur #6's App\Models\WaChatbotFlow / WaChatbotFlowStep —
 * the "flow builder" back end. Every flow is scoped to a device the
 * requesting company actually owns (checked via ownsDevice(), same
 * manual company/branch check App\Http\Controllers\Chat\
 * DeviceHealthController uses, for the same reason: this data is
 * entirely Laravel-owned, so there's no Go-side JWT ownership check to
 * lean on the way InboxController's routes do).
 *
 * Deliberately JSON-only (no Blade views yet, unlike the older
 * MessageAutoReplyController) — a real drag-and-drop step builder is a
 * substantial frontend project of its own; this gives that future UI (or
 * any other client) a complete, correct API to build against today
 * without blocking the backend engine (App\Services\Chat\
 * ChatbotFlowService) on it.
 */
class ChatbotFlowController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected DeviceDirectory $devices)
    {
    }

    /**
     * The flow builder page shell for one device — see list() for the
     * JSON data it polls.
     */
    public function index(Request $request, string $device): View
    {
        $context = $this->companyContext($request);

        if (! $this->ownsDevice($context, $device)) {
            abort(404);
        }

        return view('chat.chatbot-flows.index', ['deviceId' => $device]);
    }

    /**
     * AJAX: every flow configured for one device.
     */
    public function list(Request $request, string $device): JsonResponse
    {
        $context = $this->companyContext($request);

        if (! $this->ownsDevice($context, $device)) {
            abort(404);
        }

        $flows = WaChatbotFlow::where('device_id', $device)
            ->withCount('steps')
            ->latest()
            ->get();

        return response()->json(['flows' => $flows]);
    }

    /**
     * AJAX: one flow with its full step tree, ordered for the builder UI.
     */
    public function show(Request $request, string $device, string $flow): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);
        $flowModel->load('steps');

        return response()->json(['flow' => $flowModel]);
    }

    /**
     * AJAX: create a new (initially step-less) flow.
     */
    public function store(Request $request, string $device): JsonResponse
    {
        $context = $this->companyContext($request);

        if (! $this->ownsDevice($context, $device)) {
            abort(404);
        }

        $validated = $this->flowValidator($request)->validate();

        $flow = WaChatbotFlow::create(array_merge($validated, [
            'company_id' => $context->company->id,
            'device_id' => $device,
        ]));

        return response()->json(['flow' => $flow], 201);
    }

    /**
     * AJAX: update a flow's own attributes (name/trigger/status/timeout —
     * not its steps, see storeStep()/updateStep()/destroyStep() below).
     */
    public function update(Request $request, string $device, string $flow): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);

        $validated = $this->flowValidator($request)->validate();

        $flowModel->update($validated);

        return response()->json(['flow' => $flowModel]);
    }

    /**
     * AJAX: delete a flow — cascades to its steps and clears any customer
     * currently mid-session in it (see the FK constraints on
     * wa_chatbot_flow_steps/wa_chatbot_states).
     */
    public function destroy(Request $request, string $device, string $flow): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);
        $flowModel->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * AJAX: add a step to a flow.
     */
    public function storeStep(Request $request, string $device, string $flow): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);

        $validated = $this->stepValidator($request, $flowModel)->validate();

        if (! array_key_exists('position', $validated) || $validated['position'] === null) {
            $validated['position'] = ($flowModel->steps()->max('position') ?? -1) + 1;
        }

        $step = $flowModel->steps()->create($validated);

        if ($request->boolean('is_start')) {
            $this->makeStartStep($flowModel, $step);
        }

        return response()->json(['step' => $step], 201);
    }

    /**
     * AJAX: update one step.
     */
    public function updateStep(Request $request, string $device, string $flow, string $step): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);
        $stepModel = $flowModel->steps()->whereKey($step)->firstOrFail();

        $validated = $this->stepValidator($request, $flowModel, $stepModel)->validate();

        $stepModel->update($validated);

        if ($request->boolean('is_start')) {
            $this->makeStartStep($flowModel, $stepModel);
        }

        return response()->json(['step' => $stepModel->fresh()]);
    }

    /**
     * AJAX: delete a step. Any other step pointing at this one
     * (default_next_step_id, or a 'choice' option's next_step_id) falls
     * back to null ("end the flow here") via the FK's nullOnDelete —
     * except options[].next_step_id, which lives inside a JSON column the
     * database can't cascade into; the flow builder UI is responsible for
     * re-pointing those, same as it validates them on save (see
     * stepValidator()'s after() hook above).
     */
    public function destroyStep(Request $request, string $device, string $flow, string $step): JsonResponse
    {
        $flowModel = $this->ownedFlowOrFail($request, $device, $flow);

        $deleted = $flowModel->steps()->whereKey($step)->delete();

        if (! $deleted) {
            abort(404);
        }

        return response()->json(['status' => 'ok']);
    }

    private function ownedFlowOrFail(Request $request, string $device, string $flow): WaChatbotFlow
    {
        $context = $this->companyContext($request);

        if (! $this->ownsDevice($context, $device)) {
            abort(404);
        }

        return WaChatbotFlow::where('device_id', $device)->whereKey($flow)->firstOrFail();
    }

    private function ownsDevice(CompanyContext $context, string $deviceId): bool
    {
        $scope = $this->devices->scopeFor($deviceId);

        if ($scope['company_id'] !== $context->company->id) {
            return false;
        }

        if ($context->isLockedToBranch() && $scope['branch_office_id'] !== $context->branchOffice?->id) {
            return false;
        }

        return true;
    }

    private function flowValidator(Request $request): ValidatorContract
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'trigger_keyword' => ['required', 'string', 'max:255'],
            'trigger_match_type' => ['required', Rule::in(['exact', 'contains'])],
            'exit_keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([WaChatbotFlow::STATUS_ACTIVE, WaChatbotFlow::STATUS_INACTIVE])],
            'session_timeout_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
    }

    private function stepValidator(Request $request, WaChatbotFlow $flow, ?WaChatbotFlowStep $editing = null): ValidatorContract
    {
        $validator = Validator::make($request->all(), [
            'step_type' => ['required', Rule::in(WaChatbotFlowStep::TYPES)],
            'message' => ['nullable', 'string', 'max:4096'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string', 'max:200'],
            'options.*.value' => ['nullable', 'string', 'max:200'],
            'options.*.next_step_id' => ['nullable', 'string'],
            // Sumber pilihan OTOMATIS untuk step 'choice' -- kalau diisi,
            // daftar pilihan di-generate saat runtime dari data Jadwal
            // (lihat App\Services\Chat\ChatbotFlowService::resolveOptions())
            // dan kolom 'options' di atas boleh kosong (lihat after() hook
            // di bawah).
            'options_source' => ['nullable', Rule::in(WaChatbotFlowStep::OPTIONS_SOURCES)],
            'action' => ['nullable', Rule::in(WaChatbotFlowStep::ACTIONS)],
            'action_value' => ['nullable', 'string', 'max:255'],
            'default_next_step_id' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $validator->after(function (ValidatorContract $v) use ($request, $flow, $editing) {
            $stepType = $request->input('step_type');

            if ($stepType === WaChatbotFlowStep::TYPE_CHOICE
                && ! $request->input('options_source')
                && empty($request->input('options'))
            ) {
                $v->errors()->add('options', 'Step tipe "choice" wajib memiliki minimal 1 opsi (atau pilih Sumber Pilihan otomatis).');
            }

            if ($stepType === WaChatbotFlowStep::TYPE_ACTION && ! $request->input('action')) {
                $v->errors()->add('action', 'Step tipe "action" wajib memilih satu aksi.');
            }

            // Every step id this request references (default_next_step_id,
            // and every option's next_step_id) must belong to the SAME
            // flow — otherwise a builder UI bug (or a tampered request)
            // could wire a step to jump into an entirely different
            // company's flow.
            $referenced = array_filter(array_merge(
                [$request->input('default_next_step_id')],
                array_column($request->input('options', []), 'next_step_id')
            ));

            if (empty($referenced)) {
                return;
            }

            $validIds = $flow->steps()->whereIn('id', $referenced)->pluck('id')->all();
            $invalid = array_diff($referenced, $validIds);

            // A step is allowed to reference itself as the "editing" row
            // isn't excluded above (self-loops are a legitimate way to
            // build a "keep asking until valid" choice step), so no
            // special-casing needed for $editing here beyond having it
            // available for future rules.
            if (! empty($invalid)) {
                $v->errors()->add('default_next_step_id', 'Step tujuan tidak ditemukan pada flow ini: '.implode(', ', $invalid));
            }
        });

        return $validator;
    }

    /**
     * Marks $step as this flow's entry point, demoting any other step
     * currently marked is_start — same "whichever was just saved wins"
     * pattern App\Http\Controllers\Chat\MessageAutoReplyController::
     * demoteOtherDefaults() uses for is_default.
     */
    private function makeStartStep(WaChatbotFlow $flow, WaChatbotFlowStep $step): void
    {
        $flow->steps()->where('id', '!=', $step->id)->update(['is_start' => false]);

        if (! $step->is_start) {
            $step->update(['is_start' => true]);
        }
    }
}
