<?php

namespace App\Http\Controllers;

use App\Models\CategoryDocumentation;
use Illuminate\View\View;

/**
 * The public WhatsApp API documentation site — GET /dokumentasi, no
 * login required (see routes/web.php's top-level route group, deliberately
 * outside every `auth` middleware group in this app). Anyone with the
 * link can read it, since the whole point is for a THIRD PARTY (who
 * doesn't have — and shouldn't need — an account on this dashboard) to
 * learn how to call App\Http\Controllers\Api\WaApiSendMessageController.
 *
 * Content is entirely superadmin-managed (App\Models\CategoryDocumentation
 * / App\Models\ApiDocumentation — dashboard/superadmin/wa-api-dokumentasi),
 * this controller just reads the active subset and renders it. A single
 * page (not one route per article) — the doc set is small and skimmable
 * enough that a left-hand table of contents + anchor links reads better
 * than clicking through separate pages for a handful of endpoints.
 */
class PublicDocumentationController extends Controller
{
    public function index(): View
    {
        $categories = CategoryDocumentation::where('status', 'active')
            ->with(['apiDocumentations' => function ($query) {
                $query->where('status', 'active')->orderBy('sort_order')->orderBy('title');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('public.documentation.index', compact('categories'));
    }
}
