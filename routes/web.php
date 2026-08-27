<?php


use App\Http\Controllers\DashboardController;

use App\Http\Controllers\Superadmin\CategoryApplicationController;

use App\Http\Controllers\Superadmin\PackageController;

use App\Http\Controllers\Superadmin\LimitMetricController;

use App\Http\Controllers\Superadmin\PackageLimitController;

use App\Http\Controllers\Superadmin\VoucherController;

use App\Http\Controllers\Superadmin\VoucherUserController;

use App\Http\Controllers\Superadmin\ReferralCodeController;

use App\Http\Controllers\Superadmin\PointSettingController;

use App\Http\Controllers\Superadmin\DepositController as SuperadminDepositController;

use App\Http\Controllers\Superadmin\WalletController as SuperadminWalletController;

use App\Http\Controllers\Superadmin\AdminWalletActionController;

use App\Http\Controllers\Superadmin\LedgerEntryController;

use App\Http\Controllers\Superadmin\LedgerTransactionController;

use App\Http\Controllers\Superadmin\PaymentTransactionController;

use App\Http\Controllers\Superadmin\AuditLogController;

use App\Http\Controllers\Superadmin\QueueMonitorController;
use App\Http\Controllers\Superadmin\PaymentWebhookController;

use App\Http\Controllers\Superadmin\RoleController;

use App\Http\Controllers\Superadmin\HistoryUserLoginController;

use App\Http\Controllers\Superadmin\CategoryPackageController;

use App\Http\Controllers\Superadmin\VoucherHistoryController;

use App\Http\Controllers\Superadmin\TransactionStatusHistoryController;

use App\Http\Controllers\Superadmin\UserController as SuperadminUserController;

use App\Http\Controllers\Superadmin\CompanyController as SuperadminCompanyController;

use App\Http\Controllers\Superadmin\CompanyRoleController;

use App\Http\Controllers\Superadmin\CompanyToUserController;

use App\Http\Controllers\Superadmin\ApplicationMenuController;

use App\Http\Controllers\Superadmin\CompanyRoleMenuController;

use App\Http\Controllers\Superadmin\BranchOfficeController;

use App\Http\Controllers\Superadmin\BranchOfficeUnitController;
use App\Http\Controllers\Superadmin\AiModerationSettingController;
use App\Http\Controllers\Superadmin\WaAiBotProviderController;
use App\Http\Controllers\Superadmin\WaAiBotModelController;

use App\Http\Controllers\Superadmin\HelpCenters\CategoryHelpCenterController;

use App\Http\Controllers\Superadmin\Documentation\CategoryDocumentationController;

use App\Http\Controllers\Superadmin\Documentation\ApiDocumentationController;

use App\Http\Controllers\PublicDocumentationController;

use App\Http\Controllers\Superadmin\HelpCenters\HelpCenterController as SuperadminHelpCenterController;

use App\Http\Controllers\User\HelpCenters\HelpCenterController as UserHelpCenterController;

use App\Http\Controllers\Superadmin\Web\MetaTagController as WebMetaTagController;

use App\Http\Controllers\Superadmin\Web\CategoryArticleController as WebCategoryArticleController;

use App\Http\Controllers\Superadmin\Web\ArticleController as WebArticleController;

use App\Http\Controllers\Superadmin\Web\FaqController as WebFaqController;

use App\Http\Controllers\Superadmin\Web\CategoryVideoController as WebCategoryVideoController;

use App\Http\Controllers\Superadmin\Web\VideoController as WebVideoController;
use App\Http\Controllers\Superadmin\Web\TermConditionController as WebTermConditionController;
use App\Http\Controllers\Superadmin\Web\HeaderController as WebHeaderController;
use App\Http\Controllers\Superadmin\Web\SettingController as WebSettingController;
use App\Http\Controllers\Superadmin\Web\FeatureController as WebFeatureController;
use App\Http\Controllers\Superadmin\Web\FooterController as WebFooterController;
use App\Http\Controllers\Superadmin\WaTemplateReviewController;



// Aliased (not Superadmin\CompanyRoleController / CompanyRoleMenuController
// above) — same short class names, different namespace. Follows the
// existing precedent of Dashboard\PackageController being the one
// aliased while Superadmin\PackageController stays plain.
use App\Http\Controllers\User\Profile\CompanyController as UserCompanyController;
use App\Http\Controllers\User\Profile\CompanyRoleController as UserCompanyRoleController;
use App\Http\Controllers\User\Profile\CompanyRoleMenuController as UserCompanyRoleMenuController;
use App\Http\Controllers\User\Profile\CompanyUserController;
use App\Http\Controllers\User\Profile\ProfileController;
use App\Http\Controllers\User\Settings\PinController;
use App\Http\Controllers\User\Profile\BranchOfficeController as UserBranchOfficeController;
use App\Http\Controllers\User\Profile\BranchOfficeUnitController as UserBranchOfficeUnitController;
use App\Http\Controllers\User\History\HistoryUserController;

use App\Http\Controllers\Chat\ConnectDeviceController;
use App\Http\Controllers\Chat\WaApiKeyController;
use App\Http\Controllers\Chat\ChatLabelController;
use App\Http\Controllers\Chat\ContactController;
use App\Http\Controllers\Crm\CustomerAutomationRuleController;
use App\Http\Controllers\Crm\CustomerController;
use App\Http\Controllers\Crm\CustomerSegmentController;
use App\Http\Controllers\Crm\CustomerTagController;
use App\Http\Controllers\Crm\CustomerTaskController;
use App\Http\Controllers\Crm\DealController;
use App\Http\Controllers\Chat\CategoryPhoneBookController;
use App\Http\Controllers\Chat\PhoneBookController;
use App\Http\Controllers\Chat\WaGroupController;
use App\Http\Controllers\Chat\GoogleContactController;
use App\Http\Controllers\Chat\GoogleFormIntegrationController;
use App\Http\Controllers\Chat\InboxController;
use App\Http\Controllers\Chat\ChatbotFlowController;
use App\Http\Controllers\Chat\ChatSettingController;
use App\Http\Controllers\Chat\ConversationController;
use App\Http\Controllers\Chat\DeviceHealthController;
use App\Http\Controllers\Chat\NotificationController;
use App\Http\Controllers\Chat\OptOutController;
use App\Http\Controllers\Chat\MessageScheduleController;
use App\Http\Controllers\Chat\MessageTemplateController;
use App\Http\Controllers\Chat\CategoryTemplateController;
use App\Http\Controllers\Chat\MessageAutoReplyController;
use App\Http\Controllers\Chat\MessageQuickReplyController;
use App\Http\Controllers\Chat\AiBotController;
use App\Http\Controllers\Dashboard\PackageController as DashboardPackageController;
use App\Http\Controllers\Dashboard\PackageCheckoutController;
use App\Http\Controllers\Dashboard\VoucherRedeemController;
use App\Http\Controllers\Dashboard\WalletTransferController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return redirect()->route('login');
});

// Public WhatsApp API documentation — deliberately outside every `auth`
// middleware group in this file. A third party integrating with
// App\Http\Controllers\Api\WaApiSendMessageController doesn't have (and
// shouldn't need) an account on this dashboard just to read how to call
// it. See PublicDocumentationController and App\Models\CategoryDocumentation/
// App\Models\ApiDocumentation (managed at dashboard/superadmin/wa-api-dokumentasi).
Route::get('/dokumentasi', [PublicDocumentationController::class, 'index'])
    ->name('dokumentasi.index');

// Duitku's server-to-server webhook now lives in routes/api.php as
// POST /api/duitku/callback (moved there — the "api" route group is
// stateless by default, no CSRF middleware to exempt, and it matches
// the callback URL registered on the Duitku merchant dashboard).

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {

    // Moved from Chat > Laporan (used to be ChatReportController /
    // views/chat/reports/index.blade.php, gated behind 'active.package'
    // + 'menu.access') straight onto the main Dashboard — see
    // DashboardController::summary(). Sits at this group's top level
    // (not under the 'chat' prefix below) so every logged-in user sees
    // it regardless of package/menu-access status, same as the rest of
    // the Dashboard shell.
    Route::get('/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    // Gated behind an active package: once every voucher this user holds
    // has expired (or none was ever redeemed), 'active.package' blocks
    // every route below — including any added here later, since it's
    // applied at the group level instead of per-route. See
    // App\Http\Middleware\EnsureActivePackage for what "active" means.
    // 'menu.access' backstops what App\Models\CompanyRoleMenu already
    // governs in the sidebar — see App\Http\Middleware\EnsureMenuAccess.
    // Only ever restricts a non-owner member; the company owner is
    // unaffected, same as 'active.package' above it.
    Route::prefix('chat')->middleware(['active.package', 'menu.access'])->group(function () {
        Route::prefix('inbox/{device}')
            ->controller(InboxController::class)
            ->group(function () {
                Route::get('/', 'index')->name('inbox.index');
                Route::get('/chats', 'chats')->name('inbox.chats');
                Route::get('/chats/{jid}/messages', 'messages')->name('inbox.messages');
                Route::post('/chats/{jid}/messages', 'send')->name('inbox.send');
                Route::post('/chats/{jid}/media', 'sendMedia')->name('inbox.send-media');
                Route::post('/chats/{jid}/polls', 'sendPoll')->name('inbox.send-poll');
                Route::get('/chats/{jid}/polls/{messageId}/results', 'pollResults')->name('inbox.poll-results');
                Route::get('/media/{messageId}', 'media')->name('inbox.media');
                Route::get('/chats/{jid}/presence', 'presence')->name('inbox.presence');
                Route::get('/chats/{jid}/labels', 'labels')->name('inbox.labels');
                Route::post('/chats/{jid}/labels', 'attachLabel')->name('inbox.labels.attach');
                Route::delete('/chats/{jid}/labels/{labelId}', 'detachLabel')->name('inbox.labels.detach');
                Route::get('/chats/{jid}/notes', 'notes')->name('inbox.notes');
                Route::post('/chats/{jid}/notes', 'addNote')->name('inbox.notes.store');
                Route::put('/chats/{jid}/notes', 'updateNote')->name('inbox.notes.update');
                Route::delete('/chats/{jid}/notes', 'deleteNote')->name('inbox.notes.destroy');
                Route::get('/chats/{jid}/media-list', 'mediaList')->name('inbox.media-list');
                Route::get('/chats/{jid}/contact', 'contact')->name('inbox.contact');
                Route::post('/chats/{jid}/contact/assign', 'assignContact')->name('inbox.contact.assign');
                Route::get('/assignments', 'assignments')->name('inbox.assignments');
                Route::get('/quick-replies', 'quickReplies')->name('inbox.quick-replies');
                Route::get('/templates', 'templates')->name('inbox.templates');
            });

        // Chat ops (status/SLA/assignment) for one thread — see
        // App\Http\Controllers\Chat\ConversationController's docblock
        // for why this is a separate controller from InboxController
        // even though it shares the exact same inbox/{device}/chats/{jid}
        // URL shape as labels/notes/contact above.
        Route::prefix('inbox/{device}')
            ->controller(ConversationController::class)
            ->group(function () {
                Route::get('/chats/{jid}/conversation', 'show')->name('inbox.conversation.show');
                Route::post('/chats/{jid}/conversation/status', 'updateStatus')->name('inbox.conversation.status');
                Route::post('/chats/{jid}/conversation/assign', 'assign')->name('inbox.conversation.assign');
            });

        // Company-wide conversation queue (every device at once) — the
        // "ops dashboard" view, not scoped to one device the way
        // everything under inbox/{device} above is. '/' renders the page
        // shell (index()), '/data' is the JSON it polls (queue()) — same
        // page/data split as chat.connect-device.index vs .list.
        Route::prefix('conversations')
            ->controller(ConversationController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.conversations.index');
                Route::get('/data', 'queue')->name('chat.conversations.queue');
            });

        // Fitur #6 — multi-step chatbot flow builder. JSON CRUD for
        // App\Models\WaChatbotFlow/WaChatbotFlowStep; the actual
        // execution engine (App\Services\Chat\ChatbotFlowService) is
        // driven entirely from the incoming-message webhook, not from
        // any route here.
        Route::prefix('inbox/{device}/chatbot-flows')
            ->controller(ChatbotFlowController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chatbot-flows.index');
                Route::get('/data', 'list')->name('chatbot-flows.list');
                Route::post('/', 'store')->name('chatbot-flows.store');
                Route::get('/{flow}', 'show')->name('chatbot-flows.show');
                Route::put('/{flow}', 'update')->name('chatbot-flows.update');
                Route::delete('/{flow}', 'destroy')->name('chatbot-flows.destroy');
                Route::post('/{flow}/steps', 'storeStep')->name('chatbot-flows.steps.store');
                Route::put('/{flow}/steps/{step}', 'updateStep')->name('chatbot-flows.steps.update');
                Route::delete('/{flow}/steps/{step}', 'destroyStep')->name('chatbot-flows.steps.destroy');
            });

        Route::prefix('connect-device')
            ->controller(ConnectDeviceController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.connect-device.index');
                Route::get('/list', 'list')->name('chat.connect-device.list');
                Route::post('/add', 'add')->name('chat.connect-device.add');
                Route::get('/{device}/status', 'status')->name('chat.connect-device.status');
                Route::post('/{device}/reconnect', 'reconnect')->name('chat.connect-device.reconnect');
                Route::post('/{device}/disconnect', 'disconnect')->name('chat.connect-device.disconnect');
                Route::get('/{device}/history', 'history')->name('chat.connect-device.history');
            });

        // Device health scoring (anti-ban risk signal) — see
        // App\Http\Controllers\Chat\DeviceHealthController. Nested under
        // 'connect-device' for the single-device view (same page as
        // history above), plus one company-wide ranking endpoint for the
        // "which number should I use for this broadcast" recommendation.
        Route::prefix('connect-device')
            ->controller(DeviceHealthController::class)
            ->group(function () {
                Route::get('/{device}/health', 'show')->name('chat.connect-device.health');
                Route::get('/health-ranking', 'ranking')->name('chat.connect-device.health-ranking');
            });

        // Powers the header bell's real "pesan baru masuk" dropdown (see
        // App\Http\Controllers\Chat\NotificationController) — polled from
        // resources/views/layouts/partials/header.blade.php on every
        // dashboard page, not just while actually viewing Chat, so it
        // lives at the 'chat' prefix's top level rather than nested under
        // inbox/{device} like everything else that's scoped to one
        // device at a time.
        Route::get('/notifications/unread', [NotificationController::class, 'unread'])
            ->name('chat.notifications.unread');

        // Per-device third-party API credentials (token + secret_key) —
        // see App\Http\Controllers\Chat\WaApiKeyController and
        // App\Models\WaApiKey. Nested under the same 'connect-device'
        // prefix (not a separate top-level route) since this is managed
        // from that same Device page, one "API Key" button per row.
        Route::prefix('connect-device/{device}/api-key')
            ->controller(WaApiKeyController::class)
            ->group(function () {
                // '/' renders the dedicated API Key page (was a modal on
                // the Device list page) — 'data' is the JSON the page's
                // own JS calls to actually load the key, same split
                // InboxController uses (index() renders the shell,
                // chats()/messages() feed it data).
                Route::get('/', 'page')->name('chat.connect-device.api-key.show');
                Route::get('/data', 'data')->name('chat.connect-device.api-key.data');
                Route::post('/generate', 'generate')->name('chat.connect-device.api-key.generate');
                Route::post('/regenerate-token', 'regenerateToken')->name('chat.connect-device.api-key.regenerate-token');
                Route::post('/regenerate-secret', 'regenerateSecret')->name('chat.connect-device.api-key.regenerate-secret');
            });

        // --- WhatsApp automation: management/config screens only for
        // now (no execution engine yet — see each controller's
        // docblock). All scoped to the logged-in user's own company via
        // ownedCompanyOrFail(), same rule as every other company-owned
        // resource in this app.
        Route::prefix('message-schedules')
            ->controller(MessageScheduleController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.message-schedules.index');
                Route::get('/create', 'create')->name('chat.message-schedules.create');
                Route::post('/', 'store')->name('chat.message-schedules.store');
                Route::get('/{id}/edit', 'edit')->name('chat.message-schedules.edit');
                Route::put('/{id}', 'update')->name('chat.message-schedules.update');
                Route::delete('/{id}', 'destroy')->name('chat.message-schedules.destroy');
                Route::get('/{id}/history', 'history')->name('chat.message-schedules.history');
            });

        Route::prefix('message-templates')
            ->controller(MessageTemplateController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.message-templates.index');
                Route::get('/create', 'create')->name('chat.message-templates.create');
                Route::post('/', 'store')->name('chat.message-templates.store');
                Route::get('/{id}/edit', 'edit')->name('chat.message-templates.edit');
                Route::put('/{id}', 'update')->name('chat.message-templates.update');
                Route::delete('/{id}', 'destroy')->name('chat.message-templates.destroy');
            });

        Route::prefix('category-templates')
            ->controller(CategoryTemplateController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.category-templates.index');
                Route::get('/create', 'create')->name('chat.category-templates.create');
                Route::post('/', 'store')->name('chat.category-templates.store');
                Route::get('/{id}/edit', 'edit')->name('chat.category-templates.edit');
                Route::put('/{id}', 'update')->name('chat.category-templates.update');
                Route::delete('/{id}', 'destroy')->name('chat.category-templates.destroy');
            });

        // Broadcast opt-out list — see App\Http\Controllers\Chat\
        // OptOutController's docblock.
        Route::prefix('opt-outs')
            ->controller(OptOutController::class)
            ->group(function () {
                Route::get('/', 'page')->name('chat.opt-outs.index');
                Route::get('/data', 'list')->name('chat.opt-outs.list');
                Route::post('/', 'store')->name('chat.opt-outs.store');
                Route::delete('/{optOut}', 'destroy')->name('chat.opt-outs.destroy');
            });

        // Company-wide Chat settings (SLA minutes, broadcast throttle,
        // CSAT toggle/question) — see App\Http\Controllers\Chat\
        // ChatSettingController.
        Route::prefix('settings')
            ->controller(ChatSettingController::class)
            ->group(function () {
                Route::get('/', 'edit')->name('chat.settings.edit');
                Route::put('/', 'update')->name('chat.settings.update');
            });

        Route::prefix('chat-labels')
            ->controller(ChatLabelController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.labels.index');
                Route::post('/', 'store')->name('chat.labels.store');
                Route::put('/{id}', 'update')->name('chat.labels.update');
                Route::delete('/{id}', 'destroy')->name('chat.labels.destroy');
            });

        // "Kontak" — the CRM contact book (Chat > Kontak). See
        // App\Http\Controllers\Chat\ContactController's docblock for how
        // this differs from InboxController's per-chat contact()/
        // assignContact() (also below, under inbox/{device}).
        Route::prefix('contacts')
            ->controller(ContactController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.contacts.index');
                Route::get('/list', 'list')->name('chat.contacts.list');
                Route::put('/{contact}', 'update')->name('chat.contacts.update');

                // CRM Roadmap Fase 1 "Customer 360" — named under the
                // chat.contacts.* group (rather than its own prefix) on
                // purpose so App\Http\Middleware\EnsureMenuAccess gates it
                // with the exact same ApplicationMenu entry as the Kontak
                // page itself. Served by a different controller
                // (App\Http\Controllers\Crm\CustomerController) since it
                // reads across WaCustomer/WaConversation/WaChatNote, not
                // just WaContact — the route *name* is what the
                // permission check keys on, not which controller handles
                // it.
                Route::get('/customer/{customer}', [CustomerController::class, 'show'])->name('chat.contacts.show');
            });

        // "Tugas & Follow-up" — CRM Roadmap Fase 2. Its own top-level
        // menu entry (see 2026_08_12_210100_seed_chat_tasks_application_
        // menu.php), unlike chat.contacts.show above which deliberately
        // rides the Kontak page's permission group instead of getting
        // its own catalog entry.
        Route::prefix('tasks')
            ->controller(CustomerTaskController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.tasks.index');
                Route::post('/', 'store')->name('chat.tasks.store');
                Route::put('/{task}', 'update')->name('chat.tasks.update');
                Route::post('/{task}/complete', 'complete')->name('chat.tasks.complete');
                Route::post('/{task}/reopen', 'reopen')->name('chat.tasks.reopen');
                Route::delete('/{task}', 'destroy')->name('chat.tasks.destroy');
            });

        // "Sales Pipeline" — CRM Roadmap Fase 3. Own top-level menu
        // entry, same reasoning as chat.tasks.* above.
        Route::prefix('deals')
            ->controller(DealController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.deals.index');
                Route::post('/', 'store')->name('chat.deals.store');
                Route::get('/{deal}/edit', 'edit')->name('chat.deals.edit');
                Route::put('/{deal}', 'update')->name('chat.deals.update');
                Route::post('/{deal}/stage', 'moveStage')->name('chat.deals.move-stage');
                Route::delete('/{deal}', 'destroy')->name('chat.deals.destroy');
            });

        // CRM Roadmap Fase 4 "Segmentasi & Automation" — tag catalog +
        // per-customer attach/detach.
        Route::prefix('customer-tags')
            ->controller(CustomerTagController::class)
            ->group(function () {
                Route::post('/', 'store')->name('chat.customer-tags.store');
                Route::put('/{tag}', 'update')->name('chat.customer-tags.update');
                Route::delete('/{tag}', 'destroy')->name('chat.customer-tags.destroy');
                Route::post('/{customer}/attach', 'attach')->name('chat.customer-tags.attach');
                Route::delete('/{customer}/{tag}', 'detach')->name('chat.customer-tags.detach');
            });

        // CRM Roadmap Fase 4 — dynamic segments (saved filters, see
        // App\Models\WaCustomerSegment). Own top-level menu entry.
        Route::prefix('segments')
            ->controller(CustomerSegmentController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.segments.index');
                Route::post('/', 'store')->name('chat.segments.store');
                Route::get('/{segment}/edit', 'edit')->name('chat.segments.edit');
                Route::put('/{segment}', 'update')->name('chat.segments.update');
                Route::delete('/{segment}', 'destroy')->name('chat.segments.destroy');
                Route::get('/{segment}', 'show')->name('chat.segments.show');
            });

        // CRM Roadmap Fase 4 — trigger-based follow-up automation. Own
        // top-level menu entry.
        Route::prefix('automation-rules')
            ->controller(CustomerAutomationRuleController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.automation-rules.index');
                Route::post('/', 'store')->name('chat.automation-rules.store');
                Route::get('/{rule}/edit', 'edit')->name('chat.automation-rules.edit');
                Route::put('/{rule}', 'update')->name('chat.automation-rules.update');
                Route::post('/{rule}/toggle-active', 'toggleActive')->name('chat.automation-rules.toggle-active');
                Route::delete('/{rule}', 'destroy')->name('chat.automation-rules.destroy');
            });

        // "Kelompok" — Buku Telepon groups. See
        // App\Http\Controllers\Chat\CategoryPhoneBookController's docblock.
        Route::prefix('category-phone-books')
            ->controller(CategoryPhoneBookController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.category-phone-books.index');
                Route::get('/create', 'create')->name('chat.category-phone-books.create');
                Route::post('/', 'store')->name('chat.category-phone-books.store');
                Route::get('/{id}/edit', 'edit')->name('chat.category-phone-books.edit');
                Route::put('/{id}', 'update')->name('chat.category-phone-books.update');
                Route::delete('/{id}', 'destroy')->name('chat.category-phone-books.destroy');
                Route::get('/import/template', 'importTemplate')->name('chat.category-phone-books.import-template');
                Route::post('/import', 'import')
                    ->middleware('throttle:10,1')
                    ->name('chat.category-phone-books.import');
            });

        // "WA Group" — live, read-only browse of a device's WhatsApp
        // groups. See App\Http\Controllers\Chat\WaGroupController's docblock.
        Route::get('/wa-groups', [WaGroupController::class, 'index'])
            ->name('chat.wa-groups.index');

        // "Google Contact" — placeholder, see
        // App\Http\Controllers\Chat\GoogleContactController's docblock.
        Route::get('/google-contacts', [GoogleContactController::class, 'index'])
            ->name('chat.google-contacts.index');

        // "Buku Telepon" — the manually curated/imported phone book. See
        // App\Http\Controllers\Chat\PhoneBookController's docblock.
        Route::prefix('phone-books')
            ->controller(PhoneBookController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.phone-books.index');
                Route::get('/create', 'create')->name('chat.phone-books.create');
                Route::post('/', 'store')->name('chat.phone-books.store');
                Route::get('/{id}/edit', 'edit')->name('chat.phone-books.edit');
                Route::put('/{id}', 'update')->name('chat.phone-books.update');
                Route::delete('/{id}', 'destroy')->name('chat.phone-books.destroy');
                // "Hapus Semua" / "Hapus per Kelompok" — POST (bukan DELETE) supaya
                // literal "reset-all" tidak pernah bentrok dengan wildcard DELETE
                // /{id} di atas (beda HTTP method, jadi urutan registrasi di sini
                // tidak masalah).
                Route::post('/reset-all', 'resetAll')->name('chat.phone-books.reset-all');
                Route::post('/reset-category/{categoryId}', 'resetByCategory')->name('chat.phone-books.reset-category');
                Route::post('/{id}/blacklist', 'blacklist')->name('chat.phone-books.blacklist');
                Route::post('/{id}/unblacklist', 'unblacklist')->name('chat.phone-books.unblacklist');
                Route::get('/export', 'export')->name('chat.phone-books.export');
                Route::get('/import/template', 'importTemplate')->name('chat.phone-books.import-template');
                // "Riwayat Import" — App\Models\WaPhoneBookImport history/status
                // page for the async import job (App\Jobs\ProcessPhoneBookImport).
                Route::get('/import/history', 'importHistory')->name('chat.phone-books.import-history');
                Route::post('/import', 'import')
                    ->middleware('throttle:10,1')
                    ->name('chat.phone-books.import');
            });

        // "Third Party > Google Form" — see
        // App\Http\Controllers\Chat\GoogleFormIntegrationController's
        // docblock. The public receiving endpoint a company's own Google
        // Apps Script posts to lives separately in routes/api.php
        // (App\Http\Controllers\Api\GoogleFormWebhookController), since
        // it's authenticated purely by the webhook_token path segment,
        // not a logged-in session.
        Route::prefix('third-party/google-form')
            ->controller(GoogleFormIntegrationController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.third-party.google-form.index');
                Route::get('/create', 'create')->name('chat.third-party.google-form.create');
                Route::post('/', 'store')->name('chat.third-party.google-form.store');
                Route::get('/{id}', 'show')->name('chat.third-party.google-form.show');
                Route::get('/{id}/edit', 'edit')->name('chat.third-party.google-form.edit');
                Route::put('/{id}', 'update')->name('chat.third-party.google-form.update');
                Route::delete('/{id}', 'destroy')->name('chat.third-party.google-form.destroy');
                Route::post('/{id}/regenerate-token', 'regenerateToken')->name('chat.third-party.google-form.regenerate-token');
            });

        Route::prefix('message-auto-replies')
            ->controller(MessageAutoReplyController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.message-auto-replies.index');
                Route::post('/', 'store')->name('chat.message-auto-replies.store');
                Route::put('/{id}', 'update')->name('chat.message-auto-replies.update');
                Route::delete('/{id}', 'destroy')->name('chat.message-auto-replies.destroy');
            });

        Route::prefix('message-quick-replies')
            ->controller(MessageQuickReplyController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.message-quick-replies.index');
                Route::post('/', 'store')->name('chat.message-quick-replies.store');
                Route::put('/{id}', 'update')->name('chat.message-quick-replies.update');
                Route::delete('/{id}', 'destroy')->name('chat.message-quick-replies.destroy');
            });

        Route::prefix('ai-bots')
            ->controller(AiBotController::class)
            ->group(function () {
                Route::get('/', 'index')->name('chat.ai-bots.index');
                Route::post('/', 'store')->name('chat.ai-bots.store');
                Route::put('/{id}', 'update')->name('chat.ai-bots.update');
                Route::delete('/{id}', 'destroy')->name('chat.ai-bots.destroy');
            });
    });

});


Route::prefix('dashboard')->middleware(['auth', 'verified'])->group(function () {

    // Route::prefix('chat')->group(function () {
    //     Route::prefix('inbox/{device}')
    //         ->controller(InboxController::class)
    //         ->group(function () {
    //             Route::get('/', 'index')->name('inbox.index');
    //             Route::get('/chats', 'chats')->name('inbox.chats');
    //             Route::get('/chats/{jid}/messages', 'messages')->name('inbox.messages');
    //             Route::post('/chats/{jid}/messages', 'send')->name('inbox.send');
    //             Route::get('/chats/{jid}/presence', 'presence')->name('inbox.presence');
    //         });

    //     Route::prefix('connect-device')
    //         ->controller(ConnectDeviceController::class)
    //         ->group(function () {
    //             Route::get('/', 'index')->name('chat.connect-device.index');
    //             Route::get('/list', 'list')->name('chat.connect-device.list');
    //             Route::post('/add', 'add')->name('chat.connect-device.add');
    //             Route::get('/{device}/status', 'status')->name('chat.connect-device.status');
    //             Route::post('/{device}/reconnect', 'reconnect')->name('chat.connect-device.reconnect');
    //             Route::post('/{device}/disconnect', 'disconnect')->name('chat.connect-device.disconnect');
    //         });
    // });

    Route::prefix('setting/user')->group(function () {
        // 6-digit transaction PIN, required before Dashboard\
        // WalletTransferController allows a transfer. See
        // User\Settings\PinController.
        Route::prefix('pin')
            ->controller(PinController::class)
            ->group(function () {
                Route::get('/', 'edit')->name('user-settings.pin.edit');
                Route::put('/', 'update')->name('user-settings.pin.update');
            });
    });

    // "Profile" in the header dropdown (resources/views/layouts/
    // partials/header.blade.php). Single page, seven tabs: Profile,
    // Company, Branch Office, Unit/Divisi, Roles, Applications, Setting
    // Users — see User\Profile\ProfileController's class docblock for
    // what each one does. Replaces the old top-level /profile route and
    // the old dashboard/setting/user/company CRUD.
    Route::prefix('user/profile')
        ->controller(ProfileController::class)
        ->group(function () {
            Route::get('/', 'index')->name('profile.edit');
            Route::put('/', 'update')->name('profile.update');
        });

    // "Company" tab — a user can own more than one company now. The list
    // stays embedded on the shared profile page (ProfileController::
    // index()); create/edit/show/delete are dedicated pages here, same
    // "full page, not a modal" precedent as Setting Users. No {company}
    // ownership ambiguity: every method re-checks Company::user_id ===
    // auth id on the specific row, see User\Profile\CompanyController.
    Route::prefix('user/profile/companies')
        ->controller(UserCompanyController::class)
        ->group(function () {
            Route::get('/create', 'create')->name('profile.companies.create');
            Route::post('/', 'store')->name('profile.companies.store');
            Route::get('/{id}', 'show')->name('profile.companies.show');
            Route::get('/{id}/edit', 'edit')->name('profile.companies.edit');
            Route::put('/{id}', 'update')->name('profile.companies.update');
            Route::delete('/{id}', 'destroy')->name('profile.companies.destroy');
        });

    // "Roles" tab — CRUD scoped to the company owned by the logged in
    // user (see CompanyRoleController::ownedCompanyOrFail()). No {company}
    // route param anywhere: which company is never client-supplied.
    //
    // 'menu.access' on this whole group (and the four below it): same
    // backstop as the `chat` route group (see App\Http\Middleware\
    // EnsureMenuAccess) — a non-owner member can only reach these routes
    // if their CompanyRole has been granted a matching App\Models\
    // ApplicationMenu entry (route_name LIKE 'profile.company-roles.%',
    // etc). Fails OPEN when no such catalog entry exists yet, so this is
    // a no-op (unrestricted, same as before) until a superadmin actually
    // creates one — existing companies aren't locked out by this change.
    // The company owner is always unrestricted regardless.
    Route::prefix('user/profile/company-roles')
        ->controller(UserCompanyRoleController::class)
        ->middleware('menu.access')
        ->group(function () {
            Route::get('/create/{unitId}', 'create')->name('profile.company-roles.create');
            Route::post('/', 'store')->name('profile.company-roles.store');
            Route::get('/{id}', 'show')->name('profile.company-roles.show');
            Route::put('/{id}', 'update')->name('profile.company-roles.update');
            Route::delete('/{id}', 'destroy')->name('profile.company-roles.destroy');
        });

    // "Branch Office" tab — CRUD scoped to the company owned by the
    // logged in user (see User\Profile\BranchOfficeController::
    // ownedCompanyOrFail()). Comes right after Company in the flow: a
    // company must exist before a branch office can be created.
    Route::prefix('user/profile/branch-offices')
        ->controller(UserBranchOfficeController::class)
        ->middleware('menu.access')
        ->group(function () {
            Route::get('/create/{companyId}', 'create')->name('profile.branch-offices.create');
            Route::post('/', 'store')->name('profile.branch-offices.store');
            Route::get('/{id}', 'show')->name('profile.branch-offices.show');
            Route::put('/{id}', 'update')->name('profile.branch-offices.update');
            Route::delete('/{id}', 'destroy')->name('profile.branch-offices.destroy');
        });

    // "Unit/Divisi" tab — scoped two levels deep: unit -> branch office
    // -> company owned by the logged in user (see User\Profile\
    // BranchOfficeUnitController::ownedCompanyOrFail()). Last step of
    // the flow: a branch office must exist before a unit can be created.
    Route::prefix('user/profile/branch-office-units')
        ->controller(UserBranchOfficeUnitController::class)
        ->middleware('menu.access')
        ->group(function () {
            Route::get('/create/{branchOfficeId}', 'create')->name('profile.branch-office-units.create');
            Route::post('/', 'store')->name('profile.branch-office-units.store');
            Route::get('/{id}', 'show')->name('profile.branch-office-units.show');
            Route::put('/{id}', 'update')->name('profile.branch-office-units.update');
            Route::delete('/{id}', 'destroy')->name('profile.branch-office-units.destroy');
        });

    // "Setting Users" tab — same scoping rule as company-roles above.
    // Create/Edit are full pages, not modals — see CompanyUserController's
    // class docblock for why. Export/import-template are plain GETs (file
    // downloads, no state change, so no CSRF/throttle concern); import is
    // POST + throttled since it's the one action here that can create a
    // lot of rows from a single request — see CompanyUsersImport's
    // docblock for the rest of that endpoint's security posture.
    Route::prefix('user/profile/company-users')
        ->controller(CompanyUserController::class)
        ->middleware('menu.access')
        ->group(function () {
            Route::get('/create', 'create')->name('profile.company-users.create');
            Route::post('/', 'store')->name('profile.company-users.store');
            // Literal-segment GETs (export/import) registered BEFORE the
            // dynamic GET /{id} below — Laravel matches routes in
            // registration order, so /{id} would otherwise swallow
            // "/export" and "/import/template" as id="export" etc.
            Route::get('/export', 'export')->name('profile.company-users.export');
            Route::get('/import/template', 'importTemplate')->name('profile.company-users.import-template');
            Route::post('/import', 'import')
                ->middleware('throttle:10,1')
                ->name('profile.company-users.import');
            Route::get('/{id}', 'show')->name('profile.company-users.show');
            Route::get('/{id}/edit', 'edit')->name('profile.company-users.edit');
            Route::put('/{id}', 'update')->name('profile.company-users.update');
            Route::delete('/{id}', 'destroy')->name('profile.company-users.destroy');
        });

    // "Applications" tab — which Application Menu entries this
    // company's owner has switched on. Same scoping rule as
    // company-roles/company-users above (see CompanyRoleMenuController::
    // ownedCompanyOrFail()).
    Route::prefix('user/profile/company-role-menus')
        ->controller(UserCompanyRoleMenuController::class)
        ->middleware('menu.access')
        ->group(function () {
            Route::get('/create/{roleId}', 'create')->name('profile.company-role-menus.create');
            Route::post('/', 'store')->name('profile.company-role-menus.store');
            Route::get('/{id}', 'show')->name('profile.company-role-menus.show');
            Route::put('/{id}', 'update')->name('profile.company-role-menus.update');
            Route::delete('/{id}', 'destroy')->name('profile.company-role-menus.destroy');
        });

    // Was Route::prefix('deposit/user')->group(fn () => Route::prefix('deposit')->...)
    // — that nested TWO "deposit" segments into the URL
    // (dashboard/deposit/user/deposit/topup), so the URL a user would
    // reasonably expect (dashboard/deposit/user/topup) 404'd. Flattened
    // to a single prefix so the path matches the expectation.
    // pay() ("Simulasi Bayar" — instantly mark a deposit SUCCESS on the
    // user's own say-so) is retired now that Duitku is wired up; a
    // self-service "credit my own wallet" endpoint can't coexist with a
    // real payment gateway. checkout()/proceedToDuitku()/
    // returnFromDuitku() replace it — see DepositController's docblock.
    Route::prefix('deposit/user')
        ->controller(\App\Http\Controllers\User\Deposit\DepositController::class)
        ->group(function () {
            Route::get('/topup', 'create')->name('deposit.topup');
            Route::get('/history', 'history')->name('deposit.history');
            Route::post('/', 'store')->name('deposit.store');
            Route::get('/{deposit}/checkout', 'checkout')->name('deposit.checkout');
            Route::post('/{deposit}/checkout/duitku', 'proceedToDuitku')->name('deposit.checkout.duitku');
            Route::post('/{deposit}/checkout/cancel', 'cancelCheckout')->name('deposit.checkout.cancel');
            Route::get('/{deposit}/duitku/return', 'returnFromDuitku')->name('deposit.duitku.return');
        });

    // "Riwayat Saya" in the profile dropdown (resources/views/layouts/
    // partials/header.blade.php) — used to be a dead "Subscription"
    // link (href="#!"). One page, three tabs, all scoped to the logged
    // in user: top-up, voucher, login history.
    Route::get('/history', [HistoryUserController::class, 'index'])
        ->name('user-history.index');

    // "Help Center" — a logged-in user's own support tickets (file a
    // complaint/question, then reply back and forth with a superadmin
    // in the same thread). See User\HelpCenters\HelpCenterController;
    // Superadmin\HelpCenters\HelpCenterController is the admin side of
    // the same help_centers/help_center_answers tables.
    Route::prefix('help-center')
        ->controller(UserHelpCenterController::class)
        ->group(function () {
            Route::get('/', 'index')->name('user-help-center.index');
            Route::post('/', 'store')->name('user-help-center.store');
            Route::get('/{id}', 'show')->name('user-help-center.show');
            Route::post('/{id}/reply', 'reply')->name('user-help-center.reply');
        });

    // "Packages" in the profile dropdown (resources/views/layouts/
    // partials/header.blade.php) — used to be a dead link (href="#!").
    // Product-style catalog of active packages, filterable by category
    // application and searchable by name. Read-only for any logged in
    // user, so it's a separate controller from Superadmin\PackageController
    // (the CRUD table gated by the 'superadmin' middleware below).
    Route::get('/package', [DashboardPackageController::class, 'index'])
        ->name('dashboard.package.index');

    // Checkout for a single package: promo/referral code validation
    // (apply-promo / apply-referral, called via fetch() from
    // resources/views/dashboard/package/checkout.blade.php) plus the
    // final store() that actually charges the wallet. See
    // Dashboard\PackageCheckoutController for the full flow.
    Route::prefix('package/{package}/checkout')
        ->controller(PackageCheckoutController::class)
        ->group(function () {
            Route::get('/', 'show')->name('dashboard.package.checkout');
            Route::post('/apply-promo', 'applyPromo')->name('dashboard.package.checkout.apply-promo');
            Route::post('/apply-referral', 'applyReferral')->name('dashboard.package.checkout.apply-referral');
            Route::post('/', 'store')->name('dashboard.package.checkout.store');
        });

    Route::get('/package/invoice/{subscription}', [PackageCheckoutController::class, 'invoice'])
        ->name('dashboard.package.invoice');

    // "Sisa kuota saya" — purchased vs used vs remaining for every
    // metric the company's active package caps (see App\Services\
    // PackageLimitService::usageReport()). Linked from the "Kuota Habis"
    // notification email as well as the sidebar.
    Route::get('/package/usage', [DashboardPackageController::class, 'usage'])
        ->name('dashboard.package.usage');

    // "Redeem Voucher" in the profile dropdown (resources/views/layouts/
    // partials/header.blade.php) — activates the voucher generated by a
    // package purchase (see PackageCheckoutController::store()).
    Route::prefix('redeem-voucher')
        ->controller(VoucherRedeemController::class)
        ->group(function () {
            Route::get('/', 'index')->name('dashboard.voucher-redeem.index');
            Route::post('/', 'store')->name('dashboard.voucher-redeem.store');
        });

    // "Transfer Saldo" in the profile dropdown — 4-step wizard, see
    // Dashboard\WalletTransferController's class docblock for the full
    // security rundown (PIN required, rate-limited, deadlock-safe
    // wallet locking).
    Route::prefix('transfer')
        ->controller(WalletTransferController::class)
        ->group(function () {
            Route::get('/', 'index')->name('dashboard.wallet-transfer.index');
            Route::post('/lookup', 'lookupRecipient')->name('dashboard.wallet-transfer.lookup');
            Route::post('/', 'store')->name('dashboard.wallet-transfer.store');
            Route::get('/{transfer}/success', 'success')->name('dashboard.wallet-transfer.success');
        });

});


// NOTE: this whole superadmin group previously had NO auth/verified
// middleware at all — anyone, logged in or not, could hit these routes.
// It only "worked" by accident because none of the controllers existed
// yet (they'd 500 instead of leaking data). Now that real controllers
// exist, that gap is live, so it's fixed here. CrudAdmin itself also
// independently checks user_type === 'SUPERADMIN' on every call
// (defense in depth), but that alone still let an unauthenticated guest
// load a blank create-form page, which route-level middleware closes.
//
// 'superadmin' (App\Http\Middleware\SuperadminMiddleware, registered as
// an alias in bootstrap/app.php) was already built for exactly this —
// it just wasn't attached to this group yet. Adding it here closes the
// same class of gap for every route below, including the new
// deposit/wallet ones, which read/write directly via Eloquent instead
// of through CrudAdmin and so don't get its internal assertSuperadmin()
// check for free.
Route::prefix('dashboard')->middleware(['auth', 'verified', 'superadmin'])->group(function () {
    Route::prefix('superadmin')->group(function () {


        Route::prefix('category-application')
            ->controller(CategoryApplicationController::class)
            ->group(function () {
                Route::get('/', 'index')->name('category-application.index');
                Route::get('/create', 'create')->name('category-application.create');
                Route::post('/create', 'store')->name('category-application.store');
                Route::get('/{id}', 'show')->name('category-application.show');
                Route::get('/{id}/edit', 'edit')->name('category-application.edit');
                Route::put('/{id}', 'update')->name('category-application.update');
                Route::delete('/{id}', 'destroy')->name('category-application.destroy');
            });

        Route::prefix('package')
            ->controller(PackageController::class)
            ->group(function () {
                Route::get('/', 'index')->name('package.index');
                Route::get('/create', 'create')->name('package.create');
                Route::post('/create', 'store')->name('package.store');
                Route::get('/{id}', 'show')->name('package.show');
                Route::get('/{id}/edit', 'edit')->name('package.edit');
                Route::put('/{id}', 'update')->name('package.update');
                Route::delete('/{id}', 'destroy')->name('package.destroy');
            });


        Route::prefix('voucher')
            ->controller(VoucherController::class)
            ->group(function () {
                Route::get('/', 'index')->name('voucher.index');
                Route::get('/create', 'create')->name('voucher.create');
                Route::post('/create', 'store')->name('voucher.store');
                Route::get('/{id}', 'show')->name('voucher.show');
                Route::get('/{id}/edit', 'edit')->name('voucher.edit');
                Route::put('/{id}', 'update')->name('voucher.update');
                Route::delete('/{id}', 'destroy')->name('voucher.destroy');
            });

        // Catalog of things a Package can cap a number on (see
        // App\Models\LimitMetric's migration docblock) — deliberately
        // separate from the packages themselves so it's reusable across
        // future applications, not just Chat/Konexa.
        Route::prefix('limit-metric')
            ->controller(LimitMetricController::class)
            ->group(function () {
                Route::get('/', 'index')->name('limit-metric.index');
                Route::get('/create', 'create')->name('limit-metric.create');
                Route::post('/create', 'store')->name('limit-metric.store');
                Route::get('/{id}/edit', 'edit')->name('limit-metric.edit');
                Route::put('/{id}', 'update')->name('limit-metric.update');
                Route::delete('/{id}', 'destroy')->name('limit-metric.destroy');
            });

        // Assigns a numeric ceiling (App\Models\PackageLimit) to one
        // limit-metric on one package — e.g. "Paket A" + "broadcast_send"
        // -> 10000.
        Route::prefix('package-limit')
            ->controller(PackageLimitController::class)
            ->group(function () {
                Route::get('/', 'index')->name('package-limit.index');
                Route::get('/create', 'create')->name('package-limit.create');
                Route::post('/create', 'store')->name('package-limit.store');
                Route::get('/{id}/edit', 'edit')->name('package-limit.edit');
                Route::put('/{id}', 'update')->name('package-limit.update');
                Route::delete('/{id}', 'destroy')->name('package-limit.destroy');
            });

        // Purchase cashback/point rule — see App\Models\Setting /
        // Dashboard\PackageCheckoutController::payPurchaseCashback().
        Route::get('/point-setting', [PointSettingController::class, 'edit'])
            ->name('point-setting.edit');
        Route::put('/point-setting', [PointSettingController::class, 'update'])
            ->name('point-setting.update');

        // Shared/promo voucher codes (voucher_users table) — separate
        // from the per-user `voucher` CRUD above. See App\Models\VoucherUser.
        Route::prefix('voucher-user')
            ->controller(VoucherUserController::class)
            ->group(function () {
                Route::get('/', 'index')->name('voucher-user.index');
                Route::get('/create', 'create')->name('voucher-user.create');
                Route::post('/create', 'store')->name('voucher-user.store');
                // Must come before the /{id} routes below, otherwise
                // "redemptions" would be captured as {id}.
                Route::get('/redemptions', 'redemptions')->name('voucher-user.redemptions');
                Route::get('/{id}', 'show')->name('voucher-user.show');
                Route::get('/{id}/edit', 'edit')->name('voucher-user.edit');
                Route::put('/{id}', 'update')->name('voucher-user.update');
                Route::delete('/{id}', 'destroy')->name('voucher-user.destroy');
            });

        // Per-user referral codes (auto-created at registration — see
        // App\Models\User::boot()). No create/destroy here: superadmin
        // only edits the commission percentage, blocks/unblocks, or
        // regenerates the code for an existing user's record.
        Route::prefix('referral-code')
            ->controller(ReferralCodeController::class)
            ->group(function () {
                Route::get('/', 'index')->name('referral-code.index');
                // Must come before /{id}/edit below, otherwise
                // "usage-history" would be captured as {id}.
                Route::get('/usage-history', 'usageHistory')->name('referral-code.usage-history');
                Route::get('/{id}/edit', 'edit')->name('referral-code.edit');
                Route::put('/{id}', 'update')->name('referral-code.update');
                Route::post('/{id}/block', 'block')->name('referral-code.block');
                Route::post('/{id}/unblock', 'unblock')->name('referral-code.unblock');
                Route::post('/{id}/regenerate', 'regenerate')->name('referral-code.regenerate');
            });

        // Read-only across every user's deposits (point 1.1/1.2: view
        // all deposits, view detailed before/after ledger history).
        // Named deposits.* (plural) to match the "Data Deposits" link
        // already sitting in resources/views/layouts/partials/menu.blade.php
        // (route('deposits.index')) — that link was pointing nowhere
        // until this route existed.
        Route::prefix('deposit')
            ->controller(SuperadminDepositController::class)
            ->group(function () {
                Route::get('/', 'index')->name('deposits.index');
                Route::get('/{id}', 'show')->name('deposits.show');
            });

        // Manual balance credit/debit with history (point 1.3).
        Route::prefix('wallet')
            ->controller(SuperadminWalletController::class)
            ->group(function () {
                Route::get('/', 'index')->name('wallet.index');
                Route::get('/{walletId}/history', 'history')->name('wallet.history');
                Route::post('/{walletId}/credit', 'credit')->name('wallet.credit');
                Route::post('/{walletId}/debit', 'debit')->name('wallet.debit');
            });

        // Everything below fills in the rest of the sidebar links in
        // resources/views/layouts/partials/menu.blade.php that were
        // still pointing at routes that didn't exist yet.

        Route::get('/admin-wallet-actions', [AdminWalletActionController::class, 'index'])
            ->name('admin-wallet-actions.index');

        Route::get('/ledger-entry', [LedgerEntryController::class, 'index'])
            ->name('ledger-entry.index');

        Route::prefix('ledger-transaction')
            ->controller(LedgerTransactionController::class)
            ->group(function () {
                Route::get('/', 'index')->name('ledger-transaction.index');
                Route::get('/{id}', 'show')->name('ledger-transaction.show');
            });

        Route::get('/payment-transactions', [PaymentTransactionController::class, 'index'])
            ->name('payment-transactions.index');

        Route::get('/audit-log', [AuditLogController::class, 'index'])
            ->name('audit-log.index');

        // Read-only view over the `jobs` (pending) / `failed_jobs`
        // (gave up after exhausting retries) tables that the `database`
        // queue driver itself uses (see .env's QUEUE_CONNECTION) — lets
        // superadmin eyeball what the queue worker (supervisord:
        // teleios-worker) is actually chewing through without SSH'ing
        // into the VPS. See Superadmin\QueueMonitorController's
        // docblock.
        Route::prefix('queue-monitor')
            ->controller(QueueMonitorController::class)
            ->group(function () {
                Route::get('/', 'index')->name('queue-monitor.index');
                Route::post('/failed/retry-all', 'retryAllFailed')->name('queue-monitor.failed.retry-all');
                Route::post('/failed/{id}/retry', 'retryFailed')->name('queue-monitor.failed.retry');
                Route::delete('/failed/{id}', 'destroyFailed')->name('queue-monitor.failed.destroy');
            });

        // UAT/audit log of every Duitku payment callback (success,
        // pending, failed, our own processing errors) plus the
        // synthetic PAYMENT_EXPIRED rows from
        // App\Console\Commands\ProcessDepositExpiry — see
        // Superadmin\PaymentWebhookController's docblock.
        Route::prefix('payment-webhooks')
            ->controller(PaymentWebhookController::class)
            ->group(function () {
                Route::get('/', 'index')->name('payment-webhooks.index');
                Route::get('/{id}', 'show')->name('payment-webhooks.show');
            });

        Route::prefix('roles')
            ->controller(RoleController::class)
            ->group(function () {
                Route::get('/', 'index')->name('roles.index');
                Route::get('/create', 'create')->name('roles.create');
                Route::post('/create', 'store')->name('roles.store');
                Route::get('/{id}', 'show')->name('roles.show');
                Route::get('/{id}/edit', 'edit')->name('roles.edit');
                Route::put('/{id}', 'update')->name('roles.update');
                Route::delete('/{id}', 'destroy')->name('roles.destroy');
            });

        Route::get('/history-user-login', [HistoryUserLoginController::class, 'index'])
            ->name('history-user-login.index');

        Route::prefix('category-package')
            ->controller(CategoryPackageController::class)
            ->group(function () {
                Route::get('/', 'index')->name('category-package.index');
                Route::get('/create', 'create')->name('category-package.create');
                Route::post('/create', 'store')->name('category-package.store');
                Route::get('/{id}', 'show')->name('category-package.show');
                Route::get('/{id}/edit', 'edit')->name('category-package.edit');
                Route::put('/{id}', 'update')->name('category-package.update');
                Route::delete('/{id}', 'destroy')->name('category-package.destroy');
            });

        Route::get('/voucher-history', [VoucherHistoryController::class, 'index'])
            ->name('voucher-history.index');

        // "History Deposits" in the sidebar — status transitions, not
        // the deposits themselves (that's deposits.index above).
        Route::get('/transaction-status-history', [TransactionStatusHistoryController::class, 'index'])
            ->name('transaction-status-history.index');

        // "Data Users" in the sidebar — previously a raw url() to a
        // route that never existed.
        //
        // Named superadmin-users.* (not users.*): routes/api.php already
        // has its own unrelated `Route::get('/', 'index')->name('users.index')`
        // under Api\Superadmin\UserController (auth:sanctum). Laravel's
        // named-route table is global across web.php + api.php, so reusing
        // users.index here would silently overwrite/collide with that one —
        // whichever file loads last wins, and route('users.index') in the
        // blade views would then resolve to the Sanctum-protected API URL
        // instead of this page, which is exactly why "Data Users" wouldn't
        // open (a plain session request hitting an auth:sanctum route).
        Route::prefix('users')
            ->controller(SuperadminUserController::class)
            ->group(function () {
                Route::get('/', 'index')->name('superadmin-users.index');
                Route::get('/create', 'create')->name('superadmin-users.create');
                Route::post('/create', 'store')->name('superadmin-users.store');
                Route::get('/{id}/show', 'show')->name('superadmin-users.show');
                Route::get('/{id}/edit', 'edit')->name('superadmin-users.edit');
                Route::put('/{id}', 'update')->name('superadmin-users.update');
                Route::delete('/{id}', 'destroy')->name('superadmin-users.destroy');
                Route::post('/{id}/reset', 'reset')->name('superadmin-users.reset');
            });

        // "Data Company" in the sidebar — superadmin-wide CRUD over
        // every company (not scoped to one user like the Profile page's
        // Company tab is). See Superadmin\CompanyController.
        Route::prefix('company')
            ->controller(SuperadminCompanyController::class)
            ->group(function () {
                Route::get('/', 'index')->name('company.index');
                Route::get('/create', 'create')->name('company.create');
                Route::post('/create', 'store')->name('company.store');
                Route::get('/{id}', 'show')->name('company.show');
                Route::get('/{id}/edit', 'edit')->name('company.edit');
                Route::put('/{id}', 'update')->name('company.update');
                Route::delete('/{id}', 'destroy')->name('company.destroy');
            });

        // "Company Roles" in the sidebar — every CompanyRole across
        // every company. See Superadmin\CompanyRoleController (aliased
        // import above — not the per-user-scoped User\Profile one).
        Route::prefix('company-role')
            ->controller(CompanyRoleController::class)
            ->group(function () {
                Route::get('/', 'index')->name('company-role.index');
                Route::get('/create', 'create')->name('company-role.create');
                Route::post('/create', 'store')->name('company-role.store');
                Route::get('/{id}/edit', 'edit')->name('company-role.edit');
                Route::put('/{id}', 'update')->name('company-role.update');
                Route::delete('/{id}', 'destroy')->name('company-role.destroy');
            });

        // "Company Users" in the sidebar — every company_to_users row
        // across every company. See Superadmin\CompanyToUserController.
        Route::prefix('company-to-user')
            ->controller(CompanyToUserController::class)
            ->group(function () {
                Route::get('/', 'index')->name('company-to-user.index');
                Route::get('/create', 'create')->name('company-to-user.create');
                Route::post('/create', 'store')->name('company-to-user.store');
                Route::get('/{id}/edit', 'edit')->name('company-to-user.edit');
                Route::put('/{id}', 'update')->name('company-to-user.update');
                Route::delete('/{id}', 'destroy')->name('company-to-user.destroy');
            });

        // "Application Menus" in the sidebar — superadmin CRUD for
        // App\Models\ApplicationMenu (menu naming per Category
        // Application). See Superadmin\ApplicationMenuController.
        Route::prefix('application-menu')
            ->controller(ApplicationMenuController::class)
            ->group(function () {
                Route::get('/', 'index')->name('application-menu.index');
                Route::get('/create', 'create')->name('application-menu.create');
                Route::post('/create', 'store')->name('application-menu.store');
                Route::get('/{id}', 'show')->name('application-menu.show');
                Route::get('/{id}/edit', 'edit')->name('application-menu.edit');
                Route::put('/{id}', 'update')->name('application-menu.update');
                Route::delete('/{id}', 'destroy')->name('application-menu.destroy');
            });

        // "Provider AI" & "Model AI" in the sidebar — superadmin CRUD for
        // the AI Bot catalog (App\Models\WaAiBotProvider / WaAiBotModel).
        // This is the "menentukan apa yang bisa diajak kerja sama" catalog
        // that Chat\AiBotController's provider/model dropdowns read from.
        Route::prefix('wa-ai-bot-provider')
            ->controller(WaAiBotProviderController::class)
            ->group(function () {
                Route::get('/', 'index')->name('wa-ai-bot-provider.index');
                Route::get('/create', 'create')->name('wa-ai-bot-provider.create');
                Route::post('/create', 'store')->name('wa-ai-bot-provider.store');
                Route::get('/{id}/edit', 'edit')->name('wa-ai-bot-provider.edit');
                Route::put('/{id}', 'update')->name('wa-ai-bot-provider.update');
                Route::delete('/{id}', 'destroy')->name('wa-ai-bot-provider.destroy');
            });

        Route::prefix('wa-ai-bot-model')
            ->controller(WaAiBotModelController::class)
            ->group(function () {
                Route::get('/', 'index')->name('wa-ai-bot-model.index');
                Route::get('/create', 'create')->name('wa-ai-bot-model.create');
                Route::post('/create', 'store')->name('wa-ai-bot-model.store');
                Route::get('/{id}/edit', 'edit')->name('wa-ai-bot-model.edit');
                Route::put('/{id}', 'update')->name('wa-ai-bot-model.update');
                Route::delete('/{id}', 'destroy')->name('wa-ai-bot-model.destroy');
            });

        // "Moderasi AI" — singleton settings for App\Models\
        // AiModerationSetting, the AI that now moderates Kategori
        // Template & WA Template in place of manual superadmin approve/
        // reject. See Superadmin\AiModerationSettingController.
        Route::prefix('ai-moderation-setting')
            ->controller(AiModerationSettingController::class)
            ->group(function () {
                Route::get('/', 'edit')->name('ai-moderation-setting.edit');
                Route::put('/', 'update')->name('ai-moderation-setting.update');
            });

        // Public WhatsApp API documentation (GET /dokumentasi, no login —
        // see PublicDocumentationController) content management: two
        // catalogs, Category Documentation (sections) and Api Documentation
        // (one article per endpoint). See App\Models\CategoryDocumentation /
        // App\Models\ApiDocumentation and Superadmin\Documentation\*.
        Route::prefix('wa-api-dokumentasi')->group(function () {
            Route::prefix('categories')
                ->controller(CategoryDocumentationController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('wa-api-dokumentasi.categories.index');
                    Route::get('/create', 'create')->name('wa-api-dokumentasi.categories.create');
                    Route::post('/create', 'store')->name('wa-api-dokumentasi.categories.store');
                    Route::get('/{id}', 'show')->name('wa-api-dokumentasi.categories.show');
                    Route::get('/{id}/edit', 'edit')->name('wa-api-dokumentasi.categories.edit');
                    Route::put('/{id}', 'update')->name('wa-api-dokumentasi.categories.update');
                    Route::delete('/{id}', 'destroy')->name('wa-api-dokumentasi.categories.destroy');
                });

            Route::prefix('articles')
                ->controller(ApiDocumentationController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('wa-api-dokumentasi.articles.index');
                    Route::get('/create', 'create')->name('wa-api-dokumentasi.articles.create');
                    Route::post('/create', 'store')->name('wa-api-dokumentasi.articles.store');
                    Route::get('/{id}', 'show')->name('wa-api-dokumentasi.articles.show');
                    Route::get('/{id}/edit', 'edit')->name('wa-api-dokumentasi.articles.edit');
                    Route::put('/{id}', 'update')->name('wa-api-dokumentasi.articles.update');
                    Route::delete('/{id}', 'destroy')->name('wa-api-dokumentasi.articles.destroy');
                });
        });

        // Public web content catalog (meta tags, category-articles,
        // articles, faqs, category-videos, videos) — being built one
        // table at a time. No public/frontend routes yet, superadmin
        // CRUD only for now. See App\Models\WebMetaTag and
        // Superadmin\Web\MetaTagController.
        Route::prefix('web')->group(function () {
            Route::prefix('meta-tags')
                ->controller(WebMetaTagController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.meta-tags.index');
                    Route::get('/create', 'create')->name('web.meta-tags.create');
                    Route::post('/create', 'store')->name('web.meta-tags.store');
                    Route::get('/{id}/edit', 'edit')->name('web.meta-tags.edit');
                    Route::put('/{id}', 'update')->name('web.meta-tags.update');
                    Route::delete('/{id}', 'destroy')->name('web.meta-tags.destroy');
                });

            Route::prefix('category-articles')
                ->controller(WebCategoryArticleController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.category-articles.index');
                    Route::get('/create', 'create')->name('web.category-articles.create');
                    Route::post('/create', 'store')->name('web.category-articles.store');
                    Route::get('/{id}/edit', 'edit')->name('web.category-articles.edit');
                    Route::put('/{id}', 'update')->name('web.category-articles.update');
                    Route::delete('/{id}', 'destroy')->name('web.category-articles.destroy');
                });

            Route::prefix('articles')
                ->controller(WebArticleController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.articles.index');
                    Route::get('/create', 'create')->name('web.articles.create');
                    Route::post('/create', 'store')->name('web.articles.store');
                    Route::get('/{id}/edit', 'edit')->name('web.articles.edit');
                    Route::put('/{id}', 'update')->name('web.articles.update');
                    Route::delete('/{id}', 'destroy')->name('web.articles.destroy');
                });

            Route::prefix('faqs')
                ->controller(WebFaqController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.faqs.index');
                    Route::get('/create', 'create')->name('web.faqs.create');
                    Route::post('/create', 'store')->name('web.faqs.store');
                    Route::get('/{id}/edit', 'edit')->name('web.faqs.edit');
                    Route::put('/{id}', 'update')->name('web.faqs.update');
                    Route::delete('/{id}', 'destroy')->name('web.faqs.destroy');
                });

            Route::prefix('term-conditions')
                ->controller(WebTermConditionController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.term-conditions.index');
                    Route::get('/create', 'create')->name('web.term-conditions.create');
                    Route::post('/create', 'store')->name('web.term-conditions.store');
                    Route::get('/{id}/edit', 'edit')->name('web.term-conditions.edit');
                    Route::put('/{id}', 'update')->name('web.term-conditions.update');
                    Route::delete('/{id}', 'destroy')->name('web.term-conditions.destroy');
                });

            Route::prefix('category-videos')
                ->controller(WebCategoryVideoController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.category-videos.index');
                    Route::get('/create', 'create')->name('web.category-videos.create');
                    Route::post('/create', 'store')->name('web.category-videos.store');
                    Route::get('/{id}/edit', 'edit')->name('web.category-videos.edit');
                    Route::put('/{id}', 'update')->name('web.category-videos.update');
                    Route::delete('/{id}', 'destroy')->name('web.category-videos.destroy');
                });

            Route::prefix('videos')
                ->controller(WebVideoController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.videos.index');
                    Route::get('/create', 'create')->name('web.videos.create');
                    Route::post('/create', 'store')->name('web.videos.store');
                    Route::get('/{id}/edit', 'edit')->name('web.videos.edit');
                    Route::put('/{id}', 'update')->name('web.videos.update');
                    Route::delete('/{id}', 'destroy')->name('web.videos.destroy');
                });

            Route::prefix('headers')
                ->controller(WebHeaderController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.headers.index');
                    Route::get('/create', 'create')->name('web.headers.create');
                    Route::post('/create', 'store')->name('web.headers.store');
                    Route::get('/{id}/edit', 'edit')->name('web.headers.edit');
                    Route::put('/{id}', 'update')->name('web.headers.update');
                    Route::delete('/{id}', 'destroy')->name('web.headers.destroy');
                });

            // Singleton settings row (favicon, logo, meta tags, kontak,
            // GTM/GA, Google Maps) — see App\Models\WebSetting. Cuma
            // edit()/update(), tidak ada index/create/destroy karena
            // cuma ada satu baris (WebSetting::current()), sama seperti
            // Superadmin\AiModerationSettingController.
            Route::prefix('setting')
                ->controller(WebSettingController::class)
                ->group(function () {
                    Route::get('/', 'edit')->name('web.setting.edit');
                    Route::put('/', 'update')->name('web.setting.update');
                });

            // Fitur unggulan (App\Models\WebFeature) — flat list + 1
            // gambar per baris. Baris awalnya sudah diisi lewat
            // database/seeders/WebFeatureSeeder.php (images masih
            // kosong), CRUD ini yang dipakai untuk upload gambarnya.
            Route::prefix('features')
                ->controller(WebFeatureController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.features.index');
                    Route::get('/create', 'create')->name('web.features.create');
                    Route::post('/create', 'store')->name('web.features.store');
                    Route::get('/{id}/edit', 'edit')->name('web.features.edit');
                    Route::put('/{id}', 'update')->name('web.features.update');
                    Route::delete('/{id}', 'destroy')->name('web.features.destroy');
                });

            // Blok link footer (App\Models\WebFooter) — flat list, hanya
            // status = active yang tampil publik (lihat App\Http\
            // Controllers\Api\Frontend\FooterController).
            Route::prefix('footers')
                ->controller(WebFooterController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('web.footers.index');
                    Route::get('/create', 'create')->name('web.footers.create');
                    Route::post('/create', 'store')->name('web.footers.store');
                    Route::get('/{id}/edit', 'edit')->name('web.footers.edit');
                    Route::put('/{id}', 'update')->name('web.footers.update');
                    Route::delete('/{id}', 'destroy')->name('web.footers.destroy');
                });
        });

        // "Company Role Menus" in the sidebar — every company_role_menus
        // row across every company (which Application Menu entries each
        // company has switched on). Lets a superadmin fix a company's
        // menu setup when they report something's missing/wrong. See
        // Superadmin\CompanyRoleMenuController (aliased import above —
        // not the per-company-scoped User\Profile one).
        Route::prefix('company-role-menu')
            ->controller(CompanyRoleMenuController::class)
            ->group(function () {
                Route::get('/', 'index')->name('company-role-menu.index');
                Route::get('/create', 'create')->name('company-role-menu.create');
                Route::post('/create', 'store')->name('company-role-menu.store');
                Route::get('/{id}/edit', 'edit')->name('company-role-menu.edit');
                Route::put('/{id}', 'update')->name('company-role-menu.update');
                Route::delete('/{id}', 'destroy')->name('company-role-menu.destroy');
            });

        // "Branch Offices" in the sidebar — every branch office across
        // every company. See Superadmin\BranchOfficeController.
        Route::prefix('branch-office')
            ->controller(BranchOfficeController::class)
            ->group(function () {
                Route::get('/', 'index')->name('branch-office.index');
                Route::get('/create', 'create')->name('branch-office.create');
                Route::post('/create', 'store')->name('branch-office.store');
                Route::get('/{id}/edit', 'edit')->name('branch-office.edit');
                Route::put('/{id}', 'update')->name('branch-office.update');
                Route::delete('/{id}', 'destroy')->name('branch-office.destroy');
            });

        // "Branch Office Units" in the sidebar — every unit/divisi
        // across every branch office/company. See Superadmin\
        // BranchOfficeUnitController.
        Route::prefix('branch-office-unit')
            ->controller(BranchOfficeUnitController::class)
            ->group(function () {
                Route::get('/', 'index')->name('branch-office-unit.index');
                Route::get('/create', 'create')->name('branch-office-unit.create');
                Route::post('/create', 'store')->name('branch-office-unit.store');
                Route::get('/{id}/edit', 'edit')->name('branch-office-unit.edit');
                Route::put('/{id}', 'update')->name('branch-office-unit.update');
                Route::delete('/{id}', 'destroy')->name('branch-office-unit.destroy');
            });

        // "Help Center" — superadmin side: sees/replies to every ticket
        // across every user. See Superadmin\HelpCenters\
        // HelpCenterController and Superadmin\HelpCenters\
        // CategoryHelpCenterController (category CRUD, same shape as
        // category-application above).
        Route::prefix('help-centers/category')
            ->controller(CategoryHelpCenterController::class)
            ->group(function () {
                Route::get('/', 'index')->name('category-help-center.index');
                Route::get('/create', 'create')->name('category-help-center.create');
                Route::post('/create', 'store')->name('category-help-center.store');
                Route::get('/{id}', 'show')->name('category-help-center.show');
                Route::get('/{id}/edit', 'edit')->name('category-help-center.edit');
                Route::put('/{id}', 'update')->name('category-help-center.update');
                Route::delete('/{id}', 'destroy')->name('category-help-center.destroy');
            });

        Route::prefix('help-centers')
            ->controller(SuperadminHelpCenterController::class)
            ->group(function () {
                Route::get('/', 'index')->name('help-center.index');
                Route::get('/create', 'create')->name('help-center.create');
                Route::post('/create', 'store')->name('help-center.store');
                Route::get('/{id}', 'show')->name('help-center.show');
                Route::get('/{id}/edit', 'edit')->name('help-center.edit');
                Route::put('/{id}', 'update')->name('help-center.update');
                Route::delete('/{id}', 'destroy')->name('help-center.destroy');
                Route::post('/{id}/reply', 'reply')->name('help-center.reply');
            });

        // WA Template/Broadcast review — category list -> drill into that
        // category's templates. See Superadmin\WaTemplateReviewController.
        // Read-only oversight — categories/templates are approved/rejected
        // automatically by AI moderation now (see Chat\
        // CategoryTemplateController / Chat\MessageTemplateController),
        // so there are no more approve/reject actions here, only listing.
        // "/uncategorized" is defined before "/categories/{id}" for
        // readability (list -> uncategorized bucket -> single category
        // drill-down).
        Route::prefix('wa-templates')
            ->controller(WaTemplateReviewController::class)
            ->group(function () {
                Route::get('/', 'index')->name('wa-templates.index');
                Route::get('/uncategorized', 'uncategorized')->name('wa-templates.uncategorized');
                Route::get('/categories/{id}', 'show')->name('wa-templates.categories.show');
            });

    });

});

require __DIR__ . '/auth.php';
