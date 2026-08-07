<aside class="pe-app-sidebar" id="sidebar">
    <div class="pe-app-sidebar-logo px-5 d-flex align-items-center position-relative">
        <!--begin::Brand Image-->
        <a href="index.html" class="d-flex gap-2 logo-main">
            <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                src="{{ asset('be') }}/assets/images/favicon.png">
            <h3 class="text-white text-opacity-80 mb-0 lh-base fw-semibold">Konexa</h3>
        </a>
        <button type="button" id="sidebarDefaultArrow"
            class="btn btn-sm p-0 fs-4 ms-auto float-end d-none icon-hover-btn text-white text-opacity-60 d-none"><i
                class="ri-arrow-right-double-line"></i></button>
        <!--end::Brand Image-->
    </div>
    <nav class="pe-app-sidebar-menu nav nav-pills" data-simplebar id="sidebar-simplebar">
        <div class="d-flex align-items-start flex-column w-100">
            <ul class="pe-main-menu list-unstyled">
                <!-- Main Menu -->
                <li class="pe-menu-title">Main</li>
                <li class="pe-slide pe-has-sub">
                    <a href="{{ route('dashboard') }}" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false"
                        aria-controls="collapseDashboards" onclick="toggleCollapse('collapseAuth', this)">
                        <i class="uil uil-tachometer-fast-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Dashboards</span>
                    </a>
                </li>
                <li class="pe-menu-title">Apps</li>

                {{-- Chat menu (and its whole "Pengaturan" sub-tree) only
                         shown while the user has at least one active,
                         not-yet-expired package — same rule as the routes
                         underneath it (Route::prefix('chat')->middleware(
                         ['active.package']) in routes/web.php). $hasActivePackage
                         is injected here by a View::composer in
                         AppServiceProvider::boot() since this partial is
                         shared across every dashboard page, not rendered
                         by a single controller. --}}
                {{-- Per-role menu filter — null means unrestricted
                         (owner/superadmin/no company context), a
                         Collection of route_names means "only these".
                         See AppServiceProvider's view composer and
                         App\Models\CompanyRoleMenu. --}}
                @php
                    $canSeeChatMenu = fn(string $routeName) => $allowedChatRouteNames === null ||
                        $allowedChatRouteNames->contains($routeName);
                @endphp
                @if ($hasActivePackage)
                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseChat" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false"
                            aria-controls="collapseApplications">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Chat</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseChat">
                            {{-- <li class="pe-slide-item">
                                <a href="{{ route('chat.connect-device.index') }}" class="pe-nav-link">
                                    Pesan Masuk
                                </a>
                            </li> --}}
                            @if ($canSeeChatMenu('chat.connect-device.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.connect-device.index') }}" class="pe-nav-link">
                                        Device / Inbox
                                    </a>
                                </li>
                            @endif

                            {{-- "Pesan" moved up one level (used to sit under
                                     Chat > Pengaturan > Pesan) so it's now
                                     Chat > Pesan directly. This <li> itself is
                                     intentionally NOT wrapped in its own
                                     $canSeeChatMenu(...) check — same as every
                                     other submenu group here (e.g. "Pengaturan"
                                     below) — because it has no single route of
                                     its own to test; visibility is decided
                                     per-item by the checks already on each
                                     child link, exactly like before the move. --}}
                            <li class="pe-slide-item pe-has-sub">
                                <a href="#collapseMenuLavels2" class="pe-nav-link" data-bs-toggle="collapse"
                                    aria-expanded="false" aria-controls="collapseMenuLavels2">
                                    <span class="pe-nav-sub-content">Pesan</span>
                                    <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                                    <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                                </a>
                                <ul class="pe-slide-menu collapse" id="collapseMenuLavels2">
                                    @if ($canSeeChatMenu('chat.message-schedules.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.message-schedules.index') }}"
                                                class="pe-nav-link">
                                                Pesan Terjadwal
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.message-templates.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.message-templates.index') }}"
                                                class="pe-nav-link">
                                                WA Template
                                            </a>
                                        </li>
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.category-templates.index') }}"
                                                class="pe-nav-link">
                                                Kategori Template
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.message-auto-replies.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.message-auto-replies.index') }}"
                                                class="pe-nav-link">
                                                Auto Reply (Kata Kunci)
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.message-quick-replies.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.message-quick-replies.index') }}"
                                                class="pe-nav-link">
                                                Balasan Cepat
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.ai-bots.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.ai-bots.index') }}" class="pe-nav-link">
                                                AI Bot
                                            </a>
                                        </li>
                                    @endif
                                    {{-- "Label" moved here from the now-removed
                                             Pengaturan > Laporan submenu — that
                                             submenu was almost entirely dead
                                             links, so rather than keep an
                                             empty "Laporan" shell around just
                                             for this one working item, it's
                                             folded straight into "Pesan". --}}
                                    @if ($canSeeChatMenu('chat.labels.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.labels.index') }}" class="pe-nav-link">
                                                Label
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>

                            {{-- "Buku Telepon" raised to sit directly under
                                     Chat, alongside "Pesan" — used to be nested
                                     two levels deep under Pengaturan. Kontak
                                     here is the manually managed/imported
                                     address book (App\Models\WaPhoneBook);
                                     "Riwayat Kontak" below it is the separate,
                                     auto-populated CRM book that already
                                     existed (App\Models\WaContact) — kept
                                     under its own label so the two data
                                     sources aren't confused for one thing. --}}
                            <li class="pe-slide-item pe-has-sub">
                                <a href="#collapseBukuTelephone" class="pe-nav-link" data-bs-toggle="collapse"
                                    aria-expanded="false" aria-controls="collapseBukuTelephone">
                                    <span class="pe-nav-sub-content">Buku Telepon</span>
                                    <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                                    <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                                </a>
                                <ul class="pe-slide-menu collapse" id="collapseBukuTelephone">
                                    @if ($canSeeChatMenu('chat.phone-books.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.phone-books.index') }}" class="pe-nav-link">
                                                Kontak
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.contacts.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.contacts.index') }}" class="pe-nav-link">
                                                Riwayat Kontak
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.category-phone-books.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.category-phone-books.index') }}" class="pe-nav-link">
                                                Kelompok
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.wa-groups.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.wa-groups.index') }}" class="pe-nav-link">
                                                WA Group
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.google-contacts.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.google-contacts.index') }}" class="pe-nav-link">
                                                Google Contact
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.phone-books.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.phone-books.index', ['blacklist' => 1]) }}" class="pe-nav-link">
                                                Blacklist
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>

                            {{-- "Third Party" — sibling of "Buku Telepon", holds
                                     integrations with outside systems. Google Form
                                     (App\Models\WaFormIntegration) is the first
                                     entry; built as its own table/menu rather than
                                     a type column so future third-party types (a
                                     generic webhook, Typeform, etc.) can be added
                                     here without restructuring. --}}
                            <li class="pe-slide-item pe-has-sub">
                                <a href="#collapseThirdParty" class="pe-nav-link" data-bs-toggle="collapse"
                                    aria-expanded="false" aria-controls="collapseThirdParty">
                                    <span class="pe-nav-sub-content">Third Party</span>
                                    <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                                    <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                                </a>
                                <ul class="pe-slide-menu collapse" id="collapseThirdParty">
                                    @if ($canSeeChatMenu('chat.third-party.google-form.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.third-party.google-form.index') }}" class="pe-nav-link">
                                                Google Form
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        </ul>
                    </li>
                @endif

                @if (auth()->check() && auth()->user()->user_type === 'SUPERADMIN')
                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseUsers" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false"
                            aria-controls="collapseDashboards" onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Users</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseUsers">
                            <li class="pe-slide-item">
                                <a href="{{ route('superadmin-users.index') }}" class="pe-nav-link">
                                    Data Users
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('wallet.index') }}" class="pe-nav-link">
                                    Wallets
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('admin-wallet-actions.index') }}" class="pe-nav-link">
                                    Admin Wallet Actions
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('point-setting.edit') }}" class="pe-nav-link">
                                    Point / Cashback Setting
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('roles.index') }}" class="pe-nav-link">
                                    Roles
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('history-user-login.index') }}" class="pe-nav-link">
                                    History User Login
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="pe-slide pe-has-sub">
                        <a href="#collapsePackages" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Packages</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapsePackages">
                            <li class="pe-slide-item">
                                <a href="{{ route('package.index') }}" class="pe-nav-link">
                                    List Packages
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('category-application.index') }}" class="pe-nav-link">
                                    Category Applications
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('category-package.index') }}" class="pe-nav-link">
                                    Category Packages
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('application-menu.index') }}" class="pe-nav-link">
                                    Application Menus
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-api-dokumentasi.categories.index') }}" class="pe-nav-link">
                                    Dokumentasi API (Kategori)
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-api-dokumentasi.articles.index') }}" class="pe-nav-link">
                                    Dokumentasi API (Artikel)
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseWebContent" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-globe pe-nav-icon"></i>
                            <span class="pe-nav-content">Web Content</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseWebContent">
                            <li class="pe-slide-item">
                                <a href="{{ route('web.meta-tags.index') }}" class="pe-nav-link">
                                    Meta Tags
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.category-articles.index') }}" class="pe-nav-link">
                                    Kategori Artikel
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.articles.index') }}" class="pe-nav-link">
                                    Artikel
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.faqs.index') }}" class="pe-nav-link">
                                    FAQ
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.term-conditions.index') }}" class="pe-nav-link">
                                    Syarat & Ketentuan
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.category-videos.index') }}" class="pe-nav-link">
                                    Kategori Video
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.videos.index') }}" class="pe-nav-link">
                                    Video
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseWaTemplateReview" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-clipboard-notes pe-nav-icon"></i>
                            <span class="pe-nav-content">Review Template WA</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseWaTemplateReview">
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-templates.index') }}" class="pe-nav-link">
                                    Kategori Template
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-templates.uncategorized') }}" class="pe-nav-link">
                                    Template Tanpa Kategori
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseAiBotCatalog" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-robot pe-nav-icon"></i>
                            <span class="pe-nav-content">AI Bot</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseAiBotCatalog">
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-ai-bot-provider.index') }}" class="pe-nav-link">
                                    Provider AI
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('wa-ai-bot-model.index') }}" class="pe-nav-link">
                                    Model AI
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseCompany" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Company</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseCompany">
                            <li class="pe-slide-item">
                                <a href="{{ route('company.index') }}" class="pe-nav-link">
                                    Data Company
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('company-role.index') }}" class="pe-nav-link">
                                    Company Roles
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('company-to-user.index') }}" class="pe-nav-link">
                                    Company Users
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('company-role-menu.index') }}" class="pe-nav-link">
                                    Company Role Menus
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('branch-office.index') }}" class="pe-nav-link">
                                    Branch Offices
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('branch-office-unit.index') }}" class="pe-nav-link">
                                    Branch Office Units
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseDeposits" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Deposits</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseDeposits">
                            <li class="pe-slide-item">
                                <a href="{{ route('deposits.index') }}" class="pe-nav-link">
                                    Data Deposits
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('transaction-status-history.index') }}" class="pe-nav-link">
                                    History Deposits
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('audit-log.index') }}" class="pe-nav-link">
                                    Audit Logs
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('ledger-entry.index') }}" class="pe-nav-link">
                                    Ledger Entries
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('ledger-transaction.index') }}" class="pe-nav-link">
                                    Ledger Transaction
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('payment-transactions.index') }}" class="pe-nav-link">
                                    Payment Transaction
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('queue-monitor.index') }}" class="pe-nav-link">
                                    Queue Monitor
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('payment-webhooks.index') }}" class="pe-nav-link">
                                    Payment Callback Log
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseVouchers" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Vouchers</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseVouchers">
                            <li class="pe-slide-item">
                                <a href="{{ route('voucher.index') }}" class="pe-nav-link">
                                    List Vouchers
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('voucher-user.index') }}" class="pe-nav-link">
                                    Voucher Codes (Promo)
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('voucher-user.redemptions') }}" class="pe-nav-link">
                                    History Pemakaian Voucher
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('voucher-history.index') }}" class="pe-nav-link">
                                    History Vouchers
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseReferrals" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Referrals</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseReferrals">
                            <li class="pe-slide-item">
                                <a href="{{ route('referral-code.index') }}" class="pe-nav-link">
                                    Kode Referral User
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('referral-code.usage-history') }}" class="pe-nav-link">
                                    History Pemakaian Referral
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseHelpCenters" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-apps pe-nav-icon"></i>
                            <span class="pe-nav-content">Help Center</span>
                            <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                            <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                        </a>
                        <ul class="pe-slide-menu collapse" id="collapseHelpCenters">
                            <li class="pe-slide-item">
                                <a href="{{ route('help-center.index') }}" class="pe-nav-link">
                                    Data Tiket
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('category-help-center.index') }}" class="pe-nav-link">
                                    Kategori Help Center
                                </a>
                            </li>
                        </ul>
                    </li>
                @endif
                <li class="pe-slide pe-has-sub">
                    {{-- Was a dead static link to a demo theme page
                             (auth-signout.html) — clicking it did nothing.
                             logout is a POST route (see routes/auth.php),
                             so this needs a real form; onclick submits it
                             so the link keeps looking/behaving like every
                             other sidebar item. --}}
                    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
                        @csrf
                    </form>
                    <a href="{{ route('logout') }}" class="pe-nav-link"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="uil uil-sign-out-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Log Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</aside>
