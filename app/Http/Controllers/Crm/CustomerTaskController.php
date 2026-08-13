<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\WaCustomer;
use App\Models\WaCustomerTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Tugas & Follow-up" — CRM Roadmap Fase 2. A dated, assignable to-do
 * list tied to App\Models\WaCustomer (Fase 0's identity), separate from
 * App\Models\WaChatNote's freeform per-chat notes — see
 * database/migrations/2026_08_12_210000_create_wa_customer_tasks_table.php
 * for the full distinction.
 *
 * index() is this feature's own page (Chat > Tugas & Follow-up): every
 * task the caller is allowed to see, across every customer, so customer
 * service has one place to ask "what do I still need to follow up on".
 * store()/update()/complete()/reopen()/destroy() are also used from the
 * "Tugas & Follow-up" panel on the Customer 360 page
 * (resources/views/crm/customers/show.blade.php) — both pages post to
 * the same routes and get redirected back to wherever they came from.
 *
 * Server-rendered with plain form posts (like Chat\PhoneBookController)
 * rather than an AJAX+JSON pair (like Chat\ContactController) — a task
 * list is exactly this kind of low-frequency CRUD, so the simpler
 * pattern was the right fit here.
 */
class CustomerTaskController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $status = $request->query('status', 'pending');

        $query = WaCustomerTask::where('company_id', $company->id)
            ->with(['customer:id,name,phone', 'assignee:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        } elseif ($request->filled('branch_office_id')) {
            $query->where('branch_office_id', $request->query('branch_office_id'));
        }

        if ($status === 'pending') {
            $query->where('status', WaCustomerTask::STATUS_PENDING);
        } elseif ($status === 'done') {
            $query->where('status', WaCustomerTask::STATUS_DONE);
        }
        // status === 'all' (or anything else): no status filter.

        if ($request->query('overdue') === '1') {
            $query->overdue();
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($search = $request->query('search')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $tasks = $query
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->onEachSide(1);

        $teamMembers = $this->companyTeamMembers(
            $company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        $branches = $context->isLockedToBranch()
            ? collect()
            : BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('chat.tasks.index', [
            'tasks' => $tasks,
            'teamMembers' => $teamMembers,
            'branches' => $branches,
            'lockedBranchId' => $context->isLockedToBranch() ? $context->branchOffice?->id : null,
            'status' => $status,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'wa_customer_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $customer = WaCustomer::where('company_id', $context->company->id)
            ->where('id', $validated['wa_customer_id'])
            ->firstOrFail();

        if ($context->isLockedToBranch() && $customer->branch_office_id
            && $customer->branch_office_id !== $context->branchOffice?->id) {
            abort(403, 'Anda hanya bisa membuat tugas untuk pelanggan di branch Anda sendiri.');
        }

        WaCustomerTask::create([
            'company_id' => $context->company->id,
            'branch_office_id' => $customer->branch_office_id,
            'wa_customer_id' => $customer->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
            'status' => WaCustomerTask::STATUS_PENDING,
        ]);

        return back()->with('success', 'Tugas berhasil dibuat.');
    }

    public function update(Request $request, string $task): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findTask($context->company->id, $task);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
        ]);

        $record->update($validated);

        return back()->with('success', 'Tugas berhasil diperbarui.');
    }

    public function complete(Request $request, string $task): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findTask($context->company->id, $task);

        $record->update([
            'status' => WaCustomerTask::STATUS_DONE,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Tugas ditandai selesai.');
    }

    public function reopen(Request $request, string $task): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findTask($context->company->id, $task);

        $record->update([
            'status' => WaCustomerTask::STATUS_PENDING,
            'completed_at' => null,
        ]);

        return back()->with('success', 'Tugas dibuka kembali.');
    }

    public function destroy(Request $request, string $task): RedirectResponse
    {
        $context = $this->companyContext($request);

        $record = $this->findTask($context->company->id, $task);
        $record->delete();

        return back()->with('success', 'Tugas berhasil dihapus.');
    }

    private function findTask(string $companyId, string $taskId): WaCustomerTask
    {
        return WaCustomerTask::where('company_id', $companyId)
            ->where('id', $taskId)
            ->firstOrFail();
    }
}
