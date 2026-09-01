<header class="app-header" id="appHeader">
    <div class="container-fluid w-100 px-0 px-md-2">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center gap-2">
                <a href="index.html" class="align-items-center logo-main d-none me-5 gap-2">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/favicon.png">
                    <h3 class="text-white text-opacity-80 mb-0 lh-base fw-semibold">Konexa</h3>
                </a>
                <button type="button" class="vertical-toggle btn text-muted rounded-circle icon-btn" id="toggleSidebar"
                    aria-label="Toggle Sidebar">
                    <i class="ri-menu-2-line fs-4 toggle-left-arrow"></i>
                    <i class="ri-close-line fs-4 toggle-right-arrow d-none"></i>
                </button>
                <button type="button"
                    class="horizontal-toggle btn text-muted rounded-circle icon-btn header-btn d-none header-menu-btn"
                    id="toggleHorizontal" aria-label="Toggle Menu">
                    <i class="ri-menu-2-line fs-5 lh-sm"></i>
                </button>
            </div>
            <div class="flex-shrink-0 d-flex align-items-center gap-1 gap-md-3">

                <div class="dropdown pe-dropdown-mega d-none d-md-block">
                    <button class="btn rounded-circle text-muted icon-btn fs-5 header-menu-btn position-relative"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Messages">
                        <i class="ri-translate"></i>
                    </button>
                    <ul
                        class="dropdown-menu dropdown-menu-clickable dropdown-menu-end dropdown-mega-xs header-dropdown-menu pe-noti-dropdown-menu shadow-none border p-0">
                        <div class="card mb-0 border-0">
                            <div class="card-header p-4">
                                <h5 class="card-title">Bahasa</h5>
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/gb.svg" alt="English"
                                    width="26" height="26" class="rounded-circle"> English
                            </div>
                            
                    </ul>
                </div>
                <div class="dropdown pe-dropdown-mega">
                    <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn position-relative"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="ri-notification-4-line"></i>
                        {{-- Hidden by default — resources/views/layouts/partials/header.blade.php's
                             own script at the bottom only reveals this once the
                             real unread-chat poll (App\Http\Controllers\Chat\
                             NotificationController) actually reports unread_count > 0. --}}
                        <div class="icon-dot bg-danger d-none" id="notifBellDot"><span class="ping bg-danger"></span></div>
                    </button>
                    <div
                        class="dropdown-menu dropdown-menu-clickable dropdown-mega-md dropdown-menu-end header-dropdown-menu shadow-sm border p-0">
                        <div class="card mb-0 border-0">
                            <div class="card-header pb-5">
                                <h5 class="card-title">Notifications</h5>
                                <a href="#!" class="fw-medium">See All</a>
                            </div>
                            <div class="card-body p-0">
                                <ul class="nav nav-tabs-bordered nav-dark justify-content-evenly" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link px-2 active" data-bs-toggle="tab" href="#all"
                                            role="tab" aria-selected="false" tabindex="-1">
                                            <span>All</span>
                                            <span id="notifAllBadge"
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link px-2" data-bs-toggle="tab" href="#messages" role="tab"
                                            aria-selected="true">
                                            <span>Messages</span>
                                            <span id="notifMessagesBadge"
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">0</span>
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link px-2" data-bs-toggle="tab" href="#tasks" role="tab"
                                            aria-selected="false" tabindex="-1">
                                            <span>Tasks</span>
                                            <span
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">3</span>
                                        </a>
                                    </li>
                                    <li class="nav-item align-middle" role="presentation">
                                        <a class="nav-link px-2" data-bs-toggle="tab" href="#alerts" role="tab"
                                            aria-selected="false" tabindex="-1">
                                            <span>Alerts</span>
                                            <span
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">0</span>
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content norification-tab" id="notificationTabContent">
                                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                                        {{-- Populated by the script at the bottom of this file from
                                             App\Http\Controllers\Chat\NotificationController — real
                                             "pesan baru masuk" per chat with unread messages, not the
                                             theme's demo content. A chat drops off this list on its
                                             own next time it's polled, once opening it in Inbox marks
                                             it read (unread_count back to 0) — nothing here needs to
                                             manually remove it. --}}
                                        <div class="notification-panel" data-simplebar style="max-height: 324px;" id="notifAllList"></div>
                                        <div class="notif-empty text-center text-muted p-4 d-none" id="notifAllEmpty">Tidak ada pesan baru.</div>
                                    </div>
                                    <div class="tab-pane fade" id="messages" role="tabpanel">
                                        <div class="notification-panel" data-simplebar style="max-height: 324px;" id="notifMessagesList"></div>
                                        <div class="notif-empty text-center text-muted p-4 d-none" id="notifMessagesEmpty">Tidak ada pesan baru.</div>
                                    </div>
                                    <div class="tab-pane fade" id="tasks" role="tabpanel">
                                        <div class="notification-panel" data-simplebar style="max-height: 324px;">
                                            <div class="notification unread">
                                                <h6 class="message mb-2">Task assigned: UI Design</h6>
                                                <p class="fs-14 text-muted mb-0">Just now</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">Submit project report</h6>
                                                <p class="fs-14 text-muted mb-0">2 hours ago</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">Task completed: API Integration</h6>
                                                <p class="fs-14 text-muted mb-0">Yesterday</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">Code review: Pending approval</h6>
                                                <p class="fs-14 text-muted mb-0">1 week ago</p>
                                            </div>
                                        </div>
                                        <div class="p-4 text-end">
                                            <a href="#!" class="text-body"><i
                                                    class="ri-check-double-line fs-5 text-primary"></i> Mark all as
                                                read</a>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="alerts" role="tabpanel">
                                        <div
                                            class="d-flex flex-column justify-content-center align-items-center text-center p-5 min-h-350px">
                                            <img src="https://cdn-icons-png.flaticon.com/512/564/564619.png"
                                                alt="No Alert Box" width="70" class="mb-5" />
                                            <h5 class="mb-3">All caught up!</h5>
                                            <p class="text-muted mb-0 lh-base max-w-300px">You don’t have any new
                                                alerts right now. Keep an eye here for updates.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn d-none d-md-inline-flex"
                    type="button" id="toggleFullscreen" aria-label="Toggle fullscreen">
                    <i class="ri-fullscreen-line"></i>
                </button>
                <div id="toggleMode">
                    <button id="theme-toggle" class="btn icon-btn theme-toggle" title="Toggles light & dark theme"
                        aria-label="Switch to dark theme" aria-live="polite" type="button">
                        <svg class="sun-and-moon" aria-hidden="true" width="24" height="24"
                            viewBox="0 0 24 24">
                            <mask class="moon" id="moon-mask">
                                <rect x="0" y="0" width="100%" height="100%" fill="white" />
                                <circle cx="24" cy="10" r="6" fill="black" />
                            </mask>
                            <circle class="sun" cx="12" cy="12" r="6" mask="url(#moon-mask)"
                                fill="currentColor" />
                            <g class="sun-beams" stroke="currentColor">
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </g>
                        </svg>
                    </button>
                </div>
                <div class="dropdown pe-dropdown-mega">
                    <button class="btn p-0 gap-2 text-start d-flex align-items-center" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="text-end mt-2 d-none d-md-block">
                            <span class="user-name d-block lh-1 fs-14"> {{ Auth::user()->name }}</span>
                            <span class="text-muted fw-normal fs-13"> {{ Auth::user()->email }}</span>
                        </span>
                        <span
                            class="story-ring h-40px w-40px rounded-circle d-flex justify-content-center align-items-center">
                            <img src="{{ Auth::user()->avatarUrl() }}" alt="Avatar"
                                class="h-36px w-36px rounded-circle user-img">
                        </span>
                    </button>
                    <div
                        class="dropdown-menu dropdown-mega-xs dropdown-menu-end header-dropdown-menu shadow-sm border">
                        <div
                            class="d-flex gap-4 align-items-center justify-content-between mb-2 py-2 px-4 border rounded-2">
                            <div>
                                <h6 class="mb-1 fs-14">
                                    {{ Auth::user()->name }}
                                </h6>

                                @if (Auth::user()->referralCode)
                                    {{-- Kode + link referral milik user ini sendiri, supaya
                                         gampang dibagikan (mis. oleh agen). Link-nya menuju
                                         halaman register dengan ?ref=KODE, yang otomatis
                                         mengisi kolom kode referral di checkout siapa pun yang
                                         daftar lewat link itu — lihat Auth\AuthController::
                                         rememberReferralCodeFromLink() dan Dashboard\
                                         PackageCheckoutController::show(). Tidak mengubah cara
                                         kerja kode referral itu sendiri sama sekali, ini murni
                                         jalan pintas berbagi. Tombol copy/share dibuat plain
                                         (text-muted, tanpa border/lingkaran) supaya tidak
                                         menonjol sendiri dari warna tema aplikasi. --}}
                                    <div class="d-flex align-items-center gap-1 mb-1" id="referral-share-block" data-referral-link="{{ route('register', ['ref' => Auth::user()->referralCode->code]) }}">
                                        <span class="badge bg-primary-subtle text-primary fw-semibold fs-10 text-nowrap">
                                            {{ Auth::user()->referralCode->code }}
                                        </span>
                                        <button type="button" class="btn btn-icon btn-sm p-0 text-muted" id="referral-copy-btn" title="Salin link referral" style="width: 18px; height: 18px; line-height: 1;">
                                            <i class="ri-file-copy-line fs-11"></i>
                                        </button>
                                        <button type="button" class="btn btn-icon btn-sm p-0 text-muted d-none" id="referral-share-btn" title="Bagikan link referral" style="width: 18px; height: 18px; line-height: 1;">
                                            <i class="ri-share-forward-line fs-11"></i>
                                        </button>
                                    </div>
                                @endif

                                <small class="text-muted mb-0 d-block">
                                    {{ Auth::user()->email }}
                                </small>

                                <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                    <span class="fw-semibold fs-12 text-body text-nowrap">
                                        Rp {{ number_format(Auth::user()->wallet?->balance ?? 0, 0, ',', '.') }}
                                    </span>
                                    <a href="{{ route('deposit.topup') }}" class="btn btn-primary text-nowrap" style="padding: .15rem .5rem; font-size: .6875rem; line-height: 1.4;">
                                        <i class="ri-add-line"></i> Top Up
                                    </a>
                                </div>
                            </div>
                            <div
                                class="story-ring h-44px w-44px rounded-circle d-flex justify-content-center align-items-center">
                                <img src="{{ Auth::user()->avatarUrl() }}" alt="Avatar"
                                    class="h-40px w-40px rounded-circle">
                            </div>
                        </div>
                        <div>
                            <ul class="list-unstyled mb-0">
                                <li class="profile-item">
                                    {{-- Was a dead static link (pages-profile.html) --}}
                                    <a href="{{ route('profile.edit') }}" class="text-body">
                                        <i class="ri-user-line me-3"></i>Profile Settings
                                    </a>
                                </li>
                                <li class="profile-item">
                                    {{-- Was a dead "Packages" link (href="#!") --}}
                                    <a href="{{ route('dashboard.package.index') }}"
                                        class="text-body d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="ri-box-3-line me-3"></i>Packages
                                        </span>
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('dashboard.package.usage') }}"
                                        class="text-body d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="ri-pie-chart-2-line me-3"></i>Sisa Kuota Saya
                                        </span>
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('dashboard.voucher-redeem.index') }}"
                                        class="text-body d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="ri-coupon-3-line me-3"></i>Redeem Voucher
                                        </span>
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('dashboard.wallet-transfer.index') }}"
                                        class="text-body d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="ri-exchange-line me-3"></i>Transfer Saldo
                                        </span>
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('user-settings.pin.edit') }}"
                                        class="text-body d-flex align-items-center justify-content-between">
                                        <span>
                                            <i class="ri-shield-keyhole-line me-3"></i>PIN Transaksi
                                        </span>
                                    </a>
                                </li>
                                <li class="profile-item">
                                    {{-- Was a dead "Subscription" link (href="#!") --}}
                                    <a href="{{ route('user-history.index') }}"
                                        class="text-body d-flex align-items-center justify-content-between ">
                                        <span>
                                            <i class="ri-history-line me-3"></i>History
                                        </span>
                                    </a>
                                </li>
                                {{-- Company tab of the same consolidated profile page --}}
                                {{-- <li class="profile-item">
                                    
                                    <a href="{{ route('profile.edit', ['tab' => 'company']) }}" class="text-body">
                                        <i class="ri-building-line me-3"></i>Company
                                    </a>
                                </li> --}}
                                <li class="profile-item">
                                    {{-- Was a dead link (href="#!") — now points at
                                         User\HelpCenters\HelpCenterController. --}}
                                    <a href="{{ route('user-help-center.index') }}" class="text-body">
                                        <i class="ri-question-line me-3"></i>Help center
                                    </a>
                                </li>
                                <li class="profile-item">
                                    <a href="{{ route('logout') }}" class="text-danger"
                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="ri-logout-box-r-line me-3"></i>Sign out
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST"
                                        class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

@auth
<script>
    // Polls App\Http\Controllers\Chat\NotificationController for real
    // "pesan baru masuk" (new incoming chat message) notifications and
    // renders them into the bell dropdown above — replacing the admin
    // theme's static demo content (fake "Naomi"/"Robert" messages).
    // Included on every dashboard page (this is the shared header), not
    // just while viewing Chat, so a new message shows up no matter where
    // in the app someone currently is.
    //
    // A chat needs no explicit "dismiss" here: it simply won't be in the
    // next poll's response once its unread_count drops back to 0 (which
    // already happens today the moment that chat is actually opened in
    // Inbox — see markIncomingAsRead on the Go side), so it disappears
    // from this list on its own within one poll interval.
    (function () {
        var bellDot = document.getElementById('notifBellDot');
        var allBadge = document.getElementById('notifAllBadge');
        var messagesBadge = document.getElementById('notifMessagesBadge');
        var allList = document.getElementById('notifAllList');
        var allEmpty = document.getElementById('notifAllEmpty');
        var messagesList = document.getElementById('notifMessagesList');
        var messagesEmpty = document.getElementById('notifMessagesEmpty');

        if (!bellDot || !allList || !messagesList) return;

        var notificationsUrl = @json(route('chat.notifications.unread'));
        // '__DEVICE__' is swapped for the real device id per-notification
        // below — same placeholder-token trick resources/views/chat/inbox/
        // inbox.blade.php already uses for chat JIDs, since route()
        // doesn't enforce a device's {device} pattern when just
        // generating a URL (only when matching an incoming request).
        var inboxUrlTemplate = @json(route('inbox.index', ['device' => '__DEVICE__']));

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        // Small Indonesian relative-time formatter — no dependency on a
        // date library just for the couple of words this dropdown needs.
        function timeAgo(isoString) {
            if (!isoString) return '';
            var then = new Date(isoString).getTime();
            if (isNaN(then)) return '';
            var seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
            if (seconds < 60) return 'Baru saja';
            var minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + ' menit lalu';
            var hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + ' jam lalu';
            var days = Math.floor(hours / 24);
            if (days < 7) return days + ' hari lalu';
            return Math.floor(days / 7) + ' minggu lalu';
        }

        function notificationHref(n) {
            return inboxUrlTemplate.replace('__DEVICE__', encodeURIComponent(n.device_id))
                + '?chat=' + encodeURIComponent(n.chat_jid);
        }

        function renderList(container, emptyEl, notifications) {
            if (!notifications.length) {
                container.innerHTML = '';
                if (emptyEl) emptyEl.classList.remove('d-none');
                return;
            }
            if (emptyEl) emptyEl.classList.add('d-none');

            container.innerHTML = notifications.map(function (n) {
                var preview = n.last_message ? escapeHtml(n.last_message) : '<span class="fst-italic">(tidak ada teks)</span>';
                return '<a href="' + notificationHref(n) + '" class="notification unread d-block text-body text-decoration-none">'
                    + '<h6 class="message mb-1">Pesan baru dari ' + escapeHtml(n.name) + '</h6>'
                    + '<p class="fs-14 text-muted mb-1">' + preview + '</p>'
                    + '<p class="fs-12 text-muted mb-0">' + timeAgo(n.last_message_at) + '</p>'
                    + '</a>';
            }).join('');
        }

        function poll() {
            fetch(notificationsUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    var notifications = data.notifications || [];
                    var count = data.unread_count || 0;

                    bellDot.classList.toggle('d-none', count === 0);
                    if (allBadge) allBadge.textContent = count;
                    if (messagesBadge) messagesBadge.textContent = count;

                    renderList(allList, allEmpty, notifications);
                    renderList(messagesList, messagesEmpty, notifications);
                })
                // Silent on purpose — a logged-out session, an inactive
                // package, or a company with no Chat menu access all mean
                // "nothing to show here", not an error worth surfacing on
                // every single page's header.
                .catch(function () {});
        }

        poll();
        setInterval(poll, 15000);
    })();

    // Tombol copy/share link referral di dropdown profil (lihat markup
    // #referral-share-block di atas) — pola copy-to-clipboard-nya sama
    // persis dengan .wa-copy-btn di resources/views/chat/konekdevice/
    // api-key.blade.php: klik -> navigator.clipboard.writeText(), ikon
    // berubah jadi centang hijau sebentar sebagai feedback.
    (function () {
        var shareBlock = document.getElementById('referral-share-block');
        if (!shareBlock) return;

        var link = shareBlock.getAttribute('data-referral-link');
        var copyBtn = document.getElementById('referral-copy-btn');
        var shareBtn = document.getElementById('referral-share-btn');

        function flashCopied(btn) {
            var icon = btn.querySelector('i');
            var original = icon.className;
            icon.className = 'ri-check-line text-success fs-11';
            setTimeout(function () { icon.className = original; }, 1200);
        }

        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(link).then(function () {
                flashCopied(copyBtn);
            });
        });

        // Native share sheet (WhatsApp/dll) kalau browser-nya dukung --
        // kebanyakan di HP, jarang di desktop -- makanya tombolnya
        // disembunyikan (class d-none di markup) sampai dukungannya
        // dipastikan ada di sini.
        if (navigator.share) {
            shareBtn.classList.remove('d-none');
            shareBtn.addEventListener('click', function () {
                navigator.share({ url: link }).catch(function () {});
            });
        }
    })();
</script>
@endauth
