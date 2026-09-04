<aside class="pe-app-sidebar" id="sidebar">
    <div class="pe-app-sidebar-logo px-5 d-flex align-items-center position-relative">
        <!--begin::Brand Image-->
        <a href="{{ route('dashboard') }}" class="d-flex gap-2 logo-main">
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
                <li class="pe-slide">
                    {{--
                        Link biasa ke route('dashboard'), BUKAN toggle
                        submenu -- sebelumnya ada atribut collapse
                        Bootstrap (data-bs-toggle="collapse" dkk.) yang
                        seharusnya cuma dipakai item yang punya submenu.
                        "Dashboards" tidak punya submenu, jadi Bootstrap's
                        collapse JS selalu preventDefault() klik-nya dan
                        link ini tidak pernah benar-benar navigasi.
                        Dihapus supaya klik biasa jalan normal.
                    --}}
                    <a href="{{ route('dashboard') }}" class="pe-nav-link">
                        <i class="uil uil-tachometer-fast-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Dashboards</span>
                    </a>
                </li>
                <li class="pe-menu-title">Apps</li>

                {{--
                    Menu "Form" -- form builder Google-Forms-style per
                    branch, lihat App\Http\Controllers\Form\*.
                    Diletakkan SETELAH "Dashboards" dan SEBELUM "Jadwal"
                    (urutan: Dashboard, Form, Jadwal) sesuai spek fitur
                    ini. Sama seperti menu Jadwal di bawah: TIDAK
                    dibungkus "$hasActivePackage" (bukan bagian
                    langganan Chat), App\Http\Middleware\
                    EnsureMenuAccess tetap backstop di level route.
                    Cuma 2 level teratas (Branch, Category) yang
                    langsung ada di sidebar -- Header/Content/Footer/
                    Setting dibuka lewat tombol drill-down per baris,
                    sama seperti Jadwal.
                --}}
                <li class="pe-slide pe-has-sub">
                    <a href="#collapseForm" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false"
                        aria-controls="collapseForm">
                        <i class="uil uil-file-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Form</span>
                        <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                        <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                    </a>
                    <ul class="pe-slide-menu collapse" id="collapseForm">
                        <li class="pe-slide-item">
                            <a href="{{ route('form.branch.index') }}" class="pe-nav-link">
                                Branch
                            </a>
                        </li>
                        <li class="pe-slide-item">
                            <a href="{{ route('form.category.index') }}" class="pe-nav-link">
                                Form Category
                            </a>
                        </li>
                    </ul>
                </li>

                {{--
                    Menu "Jadwal" -- jadwal kelas kursus, generik lintas
                    bidang pendidikan (musik, bahasa, dll.), lihat
                    App\Http\Controllers\Jadwal\*. Diletakkan di atas
                    "Chat" (bukan di dalamnya) karena bukan bagian dari
                    fitur/paket Chat -- makanya juga TIDAK dibungkus
                    kondisi "$hasActivePackage" seperti Chat di bawah. Belum
                    ada filter per-role ($canSeeChatMenu-equivalent) di
                    sini -- App\Http\Middleware\EnsureMenuAccess tetap
                    jadi backstop di level route (lihat routes/web.php),
                    ini cuma belum ada baris App\Models\ApplicationMenu
                    untuk dibatasi. Tambahkan filter serupa Chat kalau
                    nanti fitur ini butuh pembatasan per-role.
                --}}
                <li class="pe-slide pe-has-sub">
                    <a href="#collapseJadwal" class="pe-nav-link" data-bs-toggle="collapse" aria-expanded="false"
                        aria-controls="collapseJadwal">
                        <i class="uil uil-calendar-alt pe-nav-icon"></i>
                        <span class="pe-nav-content">Jadwal</span>
                        <i class="ri-arrow-right-s-line pe-nav-arrow arrow-right"></i>
                        <i class="ri-arrow-left-s-line pe-nav-arrow arrow-left"></i>
                    </a>
                    <ul class="pe-slide-menu collapse" id="collapseJadwal">
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.branch.index') }}" class="pe-nav-link">
                                Branch
                            </a>
                        </li>
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="pe-nav-link">
                                Mata Pelajaran / Bidang
                            </a>
                        </li>
                        {{-- Pengajar sekarang punya menu sendiri (bukan
                                 cuma lewat drill-down "+ Add Pengajar" di
                                 index Kategori) -- permintaan user 3
                                 September 2026. Tanpa query string =
                                 mode global, lihat App\Http\Controllers\
                                 Jadwal\JadwalPengajarController::index(). --}}
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.pengajar.index') }}" class="pe-nav-link">
                                Pengajar
                            </a>
                        </li>
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.student.index') }}" class="pe-nav-link">
                                Student
                            </a>
                        </li>
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.kelas.index') }}" class="pe-nav-link">
                                Jadwal Kelas
                            </a>
                        </li>
                        <li class="pe-slide-item">
                            <a href="{{ route('jadwal.laporan.index') }}" class="pe-nav-link">
                                Laporan
                            </a>
                        </li>
                        {{-- Cuma tampil kalau company punya package aktif
                                 kategori Chat/WhatsApp secara spesifik --
                                 BUKAN $hasActivePackage yang dipakai menu
                                 Chat di bawah (itu "punya package apa
                                 saja"). Lihat App\Services\
                                 PackageLimitService::hasActiveCategoryPackage()
                                 & App\Models\JadwalReminderSetting::
                                 CHAT_CATEGORY_NAMES. Company yang cuma
                                 subscribe Jadwal tidak melihat item ini
                                 sama sekali -- konsisten dengan
                                 JadwalReminderSettingController yang juga
                                 menolak akses langsung ke route-nya. --}}
                        @if ($hasActiveChatPackage)
                            <li class="pe-slide-item">
                                <a href="{{ route('jadwal.settings.edit') }}" class="pe-nav-link">
                                    Pengaturan Pengingat
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('jadwal.reschedule-requests.index') }}" class="pe-nav-link">
                                    Permintaan Reschedule
                                </a>
                            </li>
                        @endif
                    </ul>
                </li>

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

                            {{-- Fitur #1 — antrian chat ops company-wide
                                     (status/SLA/assignee), lintas device.
                                     See App\Http\Controllers\Chat\
                                     ConversationController::index(). --}}
                            @if ($canSeeChatMenu('chat.conversations.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.conversations.index') }}" class="pe-nav-link">
                                        Percakapan
                                    </a>
                                </li>
                            @endif

                            {{-- CRM Roadmap Fase 2 "Task & Follow-up" —
                                     every open/overdue follow-up across all
                                     customers, own top-level item since it's
                                     a daily CS queue, not filed under Buku
                                     Telepon. See App\Http\Controllers\Crm\
                                     CustomerTaskController::index(). --}}
                            @if ($canSeeChatMenu('chat.tasks.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.tasks.index') }}" class="pe-nav-link">
                                        Tugas &amp; Follow-up
                                    </a>
                                </li>
                            @endif

                            {{-- CRM Roadmap Fase 3 "Sales Pipeline / Deal"
                                     — Kanban board of every open opportunity.
                                     See App\Http\Controllers\Crm\
                                     DealController::index(). --}}
                            @if ($canSeeChatMenu('chat.deals.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.deals.index') }}" class="pe-nav-link">
                                        Sales Pipeline
                                    </a>
                                </li>
                            @endif

                            {{-- CRM Roadmap Fase 4 "Segmentasi & Automation"
                                     — dynamic segments + tag catalog. See
                                     App\Http\Controllers\Crm\
                                     CustomerSegmentController::index(). --}}
                            @if ($canSeeChatMenu('chat.segments.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.segments.index') }}" class="pe-nav-link">
                                        Segmentasi
                                    </a>
                                </li>
                            @endif

                            {{-- CRM Roadmap Fase 4 — trigger-based
                                     follow-up automation rules. See
                                     App\Http\Controllers\Crm\
                                     CustomerAutomationRuleController::index(). --}}
                            @if ($canSeeChatMenu('chat.automation-rules.index'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.automation-rules.index') }}" class="pe-nav-link">
                                        Automasi
                                    </a>
                                </li>
                            @endif

                            {{-- Fitur #3 & #7 (respon/penyelesaian, performa
                                     agent, broadcast, dan CSAT) moved off
                                     this Chat submenu onto the main
                                     Dashboard — see
                                     App\Http\Controllers\DashboardController
                                     ::summary() and resources/views/
                                     dashboard/index.blade.php. --}}

                            {{-- "Pesan" moved up one level (used to sit under
                                     Chat > Pengaturan > Pesan) so it's now
                                     Chat > Pesan directly. This list item is
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
                                            <a href="{{ route('chat.message-schedules.index') }}" class="pe-nav-link">
                                                Pesan Terjadwal
                                            </a>
                                        </li>
                                    @endif
                                    @if ($canSeeChatMenu('chat.message-templates.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.message-templates.index') }}" class="pe-nav-link">
                                                WA Template
                                            </a>
                                        </li>
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.category-templates.index') }}" class="pe-nav-link">
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
                                    {{-- Fitur #2 — daftar nomor yang
                                             berhenti berlangganan broadcast.
                                             See App\Http\Controllers\Chat\
                                             OptOutController. --}}
                                    @if ($canSeeChatMenu('chat.opt-outs.index'))
                                        <li class="pe-slide-item">
                                            <a href="{{ route('chat.opt-outs.index') }}" class="pe-nav-link">
                                                Opt-out
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
                                            <a href="{{ route('chat.category-phone-books.index') }}"
                                                class="pe-nav-link">
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
                                            <a href="{{ route('chat.phone-books.index', ['blacklist' => 1]) }}"
                                                class="pe-nav-link">
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
                                            <a href="{{ route('chat.third-party.google-form.index') }}"
                                                class="pe-nav-link">
                                                Google Form
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                            </li>

                            {{-- SLA, broadcast throttle, dan CSAT — lihat
                                     App\Http\Controllers\Chat\ChatSettingController. --}}
                            @if ($canSeeChatMenu('chat.settings.edit'))
                                <li class="pe-slide-item">
                                    <a href="{{ route('chat.settings.edit') }}" class="pe-nav-link">
                                        Pengaturan
                                    </a>
                                </li>
                            @endif
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
                            <li class="pe-slide-item">
                                <a href="{{ route('frontend-visitor-log.index') }}" class="pe-nav-link">
                                    Visitor Log
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
                                <a href="{{ route('limit-metric.index') }}" class="pe-nav-link">
                                    Limit Metrics
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('package-limit.index') }}" class="pe-nav-link">
                                    Package Limits
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
                            <li class="pe-slide-item">
                                <a href="{{ route('web.headers.index') }}" class="pe-nav-link">
                                    Header
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.features.index') }}" class="pe-nav-link">
                                    Fitur
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.footers.index') }}" class="pe-nav-link">
                                    Footer
                                </a>
                            </li>
                            <li class="pe-slide-item">
                                <a href="{{ route('web.setting.edit') }}" class="pe-nav-link">
                                    Pengaturan Web
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide pe-has-sub">
                        <a href="#collapseWaTemplateReview" class="pe-nav-link" data-bs-toggle="collapse"
                            aria-expanded="false" aria-controls="collapseDashboards"
                            onclick="toggleCollapse('collapseAuth', this)">
                            <i class="uil uil-clipboard-notes pe-nav-icon"></i>
                            <span class="pe-nav-content">Log Template WA</span>
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
                            <li class="pe-slide-item">
                                <a href="{{ route('ai-moderation-setting.edit') }}" class="pe-nav-link">
                                    Moderasi AI
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
                            <li class="pe-slide-item">
                                <a href="{{ route('duitku-setting.edit') }}" class="pe-nav-link">
                                    Pengaturan Duitku
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
