<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\WaCategoryTemplate;
use App\Models\WaMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Superadmin review screen for the WA Template builder (Chat\
 * CategoryTemplateController / Chat\MessageTemplateController on the
 * company side). Two-step drill-down per the requested UX: index() lists
 * every company's categories, show() drills into one category's
 * templates. Uncategorized templates (wa_category_template_id null —
 * either created before the category builder existed, or a company
 * chose "Tanpa kategori") get their own listing since they still need
 * review but have no category to drill in from.
 *
 * Every approve/reject write goes through CrudAdmin::update() rather
 * than touching the model directly — that's what gives every review
 * decision an audit_logs entry (who approved/rejected what, and when)
 * for free, on top of the superadmin-only guard CrudAdmin already
 * enforces internally.
 */
class WaTemplateReviewController extends Controller
{
    public function index(Request $request): View
    {
        $categories = WaCategoryTemplate::with('company')
            ->withCount('templates')
            ->when($request->filled('review_status'), function ($query) use ($request) {
                $query->where('review_status', $request->string('review_status'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $uncategorizedCount = WaMessageTemplate::whereNull('wa_category_template_id')
            ->where('review_status', 'in_review')
            ->count();

        return view('superadmin.wa-templates.index', compact('categories', 'uncategorizedCount'));
    }

    public function show(string $id): View
    {
        $category = WaCategoryTemplate::with('company', 'reviewedBy')->findOrFail($id);

        $templates = WaMessageTemplate::where('wa_category_template_id', $id)
            ->latest()
            ->paginate(15);

        return view('superadmin.wa-templates.show', compact('category', 'templates'));
    }

    public function uncategorized(): View
    {
        $templates = WaMessageTemplate::with('company')
            ->whereNull('wa_category_template_id')
            ->latest()
            ->paginate(15);

        return view('superadmin.wa-templates.uncategorized', compact('templates'));
    }

    public function approveCategory(string $id): RedirectResponse
    {
        CrudAdmin::update(WaCategoryTemplate::class, $id, [
            'review_status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Kategori disetujui.');
    }

    public function rejectCategory(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        CrudAdmin::update(WaCategoryTemplate::class, $id, [
            'review_status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Kategori ditolak.');
    }

    public function approveTemplate(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(WaMessageTemplate::class, $id, [
            'review_status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Template disetujui.');
    }

    public function rejectTemplate(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        CrudAdmin::update(WaMessageTemplate::class, $id, [
            'review_status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Template ditolak.');
    }
}
