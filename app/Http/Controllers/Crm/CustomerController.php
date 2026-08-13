<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaChatNote;
use App\Models\WaConversation;
use App\Models\WaCustomer;
use App\Models\WaCustomerTag;
use App\Models\WaCustomerTask;
use App\Models\WaDeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRM Roadmap Fase 1 "Customer 360" — a single read-focused page that
 * pulls a customer's identity (App\Models\WaCustomer, Fase 0) together
 * with every WhatsApp thread and internal note attached to it, so
 * customer service doesn't have to jump between Kontak, Buku Telepon,
 * and Inbox to get the full picture of who they're talking to.
 *
 * Deliberately read-only and additive: it composes existing data
 * (App\Models\WaConversation, App\Models\WaChatNote) rather than
 * introducing a new source of truth, and links out to Inbox
 * (chat.inbox) for anything that needs the live Go-backend message
 * transcript or reply actions.
 *
 * There is intentionally no "transaksi" (transaction/order) section
 * with real data — no such model exists anywhere in this app (every
 * billing/subscription model here is scoped to the Konexa subscriber,
 * not their end customers) — the view shows an honest empty state
 * instead of fabricating one.
 */
class CustomerController extends Controller
{
    use ResolvesCompanyContext;

    public function show(Request $request, string $customer): View
    {
        $context = $this->companyContext($request);

        /** @var WaCustomer $record */
        $record = WaCustomer::where('company_id', $context->company->id)
            ->where('id', $customer)
            ->with([
                'contact',
                'phoneBookEntry.category',
                'phoneBookEntry.branchOffice',
                'assignee:id,name',
                'branchOffice:id,name',
                'tags',
            ])
            ->firstOrFail();

        // A branch-locked member may only view customers in their own
        // branch, or ones not yet triaged into any branch — same rule
        // ContactController/PhoneBookController already apply to the
        // list pages this page is linked from.
        if ($context->isLockedToBranch()
            && $record->branch_office_id
            && $record->branch_office_id !== $context->branchOffice?->id) {
            abort(403, 'Anda hanya bisa melihat pelanggan di branch Anda sendiri.');
        }

        $conversations = collect();
        $notes = collect();

        if ($record->contact) {
            $conversations = WaConversation::where('company_id', $context->company->id)
                ->where('contact_id', $record->contact->id)
                ->with('assignee:id,name')
                ->orderByDesc('last_inbound_at')
                ->get();

            if ($conversations->isNotEmpty()) {
                $deviceIds = $conversations->pluck('device_id')->unique()->values();

                // wa_devices is Go-backend-owned (no Eloquent model here —
                // same reasoning as App\Services\Chat\DeviceDirectory's
                // docblock), so this is a plain read-only query builder
                // call just to label each conversation with which WA
                // number handled it.
                $devicePhones = DB::table('wa_devices')
                    ->whereIn('id', $deviceIds)
                    ->pluck('phone_number', 'id');

                $conversations->each(function (WaConversation $conversation) use ($devicePhones) {
                    $conversation->device_phone_number = $devicePhones->get($conversation->device_id);
                });

                // A "chat" has no row of its own in Laravel's database —
                // App\Models\WaChatNote is keyed by (device_id, chat_jid),
                // the same pairing WaConversation uses, so every distinct
                // pair across this customer's conversations is one OR
                // branch here. Bounded by the customer's own conversation
                // count, never unbounded.
                $pairs = $conversations
                    ->map(fn (WaConversation $c) => ['device_id' => $c->device_id, 'chat_jid' => $c->chat_jid])
                    ->unique(fn (array $p) => $p['device_id'].'|'.$p['chat_jid'])
                    ->values();

                $notes = WaChatNote::where('company_id', $context->company->id)
                    ->where(function ($query) use ($pairs) {
                        foreach ($pairs as $pair) {
                            $query->orWhere(function ($q) use ($pair) {
                                $q->where('device_id', $pair['device_id'])
                                    ->where('chat_jid', $pair['chat_jid']);
                            });
                        }
                    })
                    ->with('author:id,name')
                    ->latest()
                    ->get();
            }
        }

        // CRM Roadmap Fase 2 — this customer's follow-up tasks, open ones
        // first (soonest due date first), so the panel doubles as "what
        // still needs doing for this person" without a second page trip.
        $tasks = WaCustomerTask::where('company_id', $context->company->id)
            ->where('wa_customer_id', $record->id)
            ->with('assignee:id,name')
            ->orderByRaw("status = 'done'")
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->latest('created_at')
            ->get();

        // CRM Roadmap Fase 3 — every sales opportunity for this customer,
        // open ones first, so the panel doubles as a mini pipeline view
        // without leaving the page.
        $deals = WaDeal::where('company_id', $context->company->id)
            ->where('wa_customer_id', $record->id)
            ->with('assignee:id,name')
            ->orderByRaw("stage IN ('won','lost')")
            ->latest('created_at')
            ->get();

        // CRM Roadmap Fase 4 — every tag in the company's catalog, so the
        // "add tag" dropdown can offer whatever this customer doesn't
        // already have.
        $availableTags = WaCustomerTag::where('company_id', $context->company->id)
            ->orderBy('name')
            ->get();

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return view('crm.customers.show', [
            'customer' => $record,
            'conversations' => $conversations,
            'notes' => $notes,
            'tasks' => $tasks,
            'deals' => $deals,
            'availableTags' => $availableTags,
            'teamMembers' => $teamMembers,
        ]);
    }
}
