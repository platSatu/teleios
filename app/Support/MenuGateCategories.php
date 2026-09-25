<?php

namespace App\Support;

/**
 * Category_applications.name lists for gating the "Form" and "Jadwal"
 * sidebar sections (resources/views/layouts/partials/menu.blade.php) and
 * their route groups (routes/web.php's 'form'/'jadwal' prefixes) behind
 * App\Services\PackageLimitService::hasActiveCategoryPackage().
 *
 * Mirrors App\Models\JadwalReminderSetting::CHAT_CATEGORY_NAMES's exact
 * reasoning (single source of truth so the category list isn't
 * duplicated across composer/controller/job) but lives in its own class
 * rather than being added onto JadwalReminderSetting — that model's own
 * docblock scopes CHAT_CATEGORY_NAMES specifically to the Jadwal
 * reminder-notification sub-feature (a different, narrower concern: "is
 * WhatsApp reachable for this notification"), not to gating the Form/
 * Jadwal MODULES themselves.
 *
 * Decision (22 September 2026, checklist "pisahkan layanan Form/Jadwal/
 * WhatsApp"): Form and Jadwal have no usage limit of their own (unlike
 * WhatsApp Blast, which is metered via PackageLimit/CompanyLimitUsage) —
 * they're gated purely on "does the company have an active package in
 * this category right now", show the menu/allow the routes if yes,
 * hide/block if no. A company holding only a WhatsApp Blast package
 * does NOT get Form/Jadwal access for free — each is sold and gated
 * independently, deliberately WITHOUT App\Http\Middleware\
 * EnsureActivePackage's usual "untagged package passes every filter"
 * backward-compat concession implying WhatsApp-only access is enough
 * (that concession still applies here only for a package that was never
 * tagged with ANY category at all, same as every other category gate —
 * it does not make a WhatsApp-tagged package pass a Form/Jadwal filter).
 *
 * routes/web.php's 'active.package:Form' / 'active.package:Jadwal'
 * middleware arguments are plain strings (Laravel route middleware
 * parameters can't reference a PHP class constant), so they duplicate
 * these values by necessity — same accepted duplication as the existing
 * 'active.package:Chat,WhatsApp,Whatsapp Blast' route gate versus
 * CHAT_CATEGORY_NAMES. Keep both in sync if a category is ever renamed.
 */
class MenuGateCategories
{
    public const FORM_CATEGORY_NAMES = ['Form'];

    public const JADWAL_CATEGORY_NAMES = ['Jadwal'];

    /**
     * Layanan Tagihan (invoice & pembayaran pelanggan). "Pembayaran" ikut
     * diterima -- nama category ini sempat dipakai saat fitur Tagihan
     * dibahas pertama kali (lihat routes/web.php grup 'tagihan').
     */
    public const TAGIHAN_CATEGORY_NAMES = ['Tagihan', 'Pembayaran'];
}
