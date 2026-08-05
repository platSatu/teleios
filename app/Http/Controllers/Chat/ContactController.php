<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\WaContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Kontak" — a company's CRM contact book (Chat > Kontak). Separate from
 * InboxController's contact()/assignContact() (scoped to one device+chat,
 * called from the Inbox detail panel) — this is the company-wide browse/
 * manage view, same split ChatLabelController (catalog) has from
 * InboxController's labels()/attachLabel()/detachLabel() (per-chat
 * tagging).
 *
 * Branch scoping follows CompanyContext exactly like every other company-
 * owned resource in this app: an owner sees/manages every contact, a
 * branch-locked member only their own branch's (plus any contact with no
 * branch set at all, since those haven't been triaged yet and every
 * branch should be able to claim one).
 */
class ContactController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);

        $branches = BranchOffice::where('company_id', $context->company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return view('chat.contacts.index', [
            'branches' => $branches,
            'teamMembers' => $teamMembers,
            'lockedBranchId' => $context->isLockedToBranch() ? $context->branchOffice?->id : null,
        ]);
    }

    /**
     * AJAX: every contact the caller is allowed to see, newest-contacted
     * first. ?branch_office_id= / ?assigned_to= / ?search= narrow it
     * further (all optional, combinable).
     */
    public function list(Request $request): JsonResponse
    {
        $context = $this->companyContext($request);

        $query = WaContact::with(['branchOffice:id,name', 'assignee:id,name'])
            ->where('company_id', $context->company->id);

        if ($context->isLockedToBranch()) {
            // A branch-locked member sees their own branch's contacts,
            // plus anything not yet triaged into a branch at all — never
            // another branch's.
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        } elseif ($request->filled('branch_office_id')) {
            $query->where('branch_office_id', $request->query('branch_office_id'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->orderByDesc('last_contacted_at')->limit(500)->get();

        return response()->json([
            'contacts' => $contacts->map(fn (WaContact $c) => $this->present($c)),
        ]);
    }

    /**
     * AJAX: edit a contact's name, branch, and/or assignee. Every field
     * is optional in the request — only what's actually sent gets
     * updated, so the Kontak page's per-cell inline editors don't have to
     * resend the whole row.
     */
    public function update(Request $request, string $contact): JsonResponse
    {
        $context = $this->companyContext($request);

        $record = WaContact::where('company_id', $context->company->id)
            ->where('id', $contact)
            ->firstOrFail();

        // A branch-locked member may only hand a contact to their own
        // branch (or un-triage it back to null) — never move it to a
        // branch they can't see themselves.
        if ($context->isLockedToBranch() && $request->filled('branch_office_id')
            && $request->input('branch_office_id') !== $context->branchOffice?->id) {
            abort(403, 'Anda hanya bisa mengelola kontak di branch Anda sendiri.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'branch_office_id' => ['sometimes', 'nullable', 'uuid', 'exists:branch_offices,id'],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
        ]);

        $record->update($validated);
        $record->load(['branchOffice:id,name', 'assignee:id,name']);

        return response()->json(['contact' => $this->present($record)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WaContact $contact): array
    {
        return [
            'id' => $contact->id,
            'phone' => $contact->phone,
            'name' => $contact->name,
            'branch_office_id' => $contact->branch_office_id,
            'branch_office_name' => $contact->branchOffice?->name,
            'assigned_to' => $contact->assigned_to,
            'assigned_to_name' => $contact->assignee?->name,
            'source' => $contact->source,
            'last_contacted_at' => $contact->last_contacted_at?->toIso8601String(),
        ];
    }
}
