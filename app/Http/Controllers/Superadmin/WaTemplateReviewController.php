<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\WaCategoryTemplate;
use App\Models\WaMessageTemplate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only oversight for the WA Template builder (Chat\
 * CategoryTemplateController / Chat\MessageTemplateController on the
 * company side). Categories and templates are no longer manually
 * approved/rejected here — every create/edit is judged straight away by
 * App\Services\Moderation\TemplateModerationService (the superadmin-
 * configured AI, see App\Models\AiModerationSetting), which writes
 * `review_status`/`rejection_reason`/`reviewed_*` itself. This controller
 * only lists what the AI decided, for a superadmin who wants to check
 * on it — it never writes to those columns.
 *
 * Two-step drill-down per the original UX: index() lists every company's
 * categories, show() drills into one category's templates. Uncategorized
 * templates (wa_category_template_id null — either created before the
 * category builder existed, or a company chose "Tanpa kategori") get
 * their own listing.
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
            ->where('review_status', 'pending')
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
}
