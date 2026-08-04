<header class="app-header" id="appHeader">
    <div class="container-fluid w-100 px-0 px-md-2">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center gap-2">
                <a href="index.html" class="align-items-center logo-main d-none me-5 gap-2">
                    <img height="33" width="33" class="logo-dark" alt="Dark Logo"
                        src="{{ asset('be') }}/assets/images/logo-md.png">
                    <h3 class="text-white text-opacity-80 mb-0 lh-base fw-semibold">Mirbal</h3>
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
                                <h5 class="card-title">Languages</h5>
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/gb.svg" alt="English"
                                    width="26" height="26" class="rounded-circle"> English
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/fr.svg" alt="French"
                                    width="26" height="26" class="rounded-circle"> French
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/us.svg" alt="Spanish"
                                    width="26" height="26" class="rounded-circle"> Spanish
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/de.svg" alt="German"
                                    width="26" height="26" class="rounded-circle"> German
                            </div>
                            <div class="noti-item d-flex align-items-center gap-2 py-2">
                                <img src="{{ asset('be') }}/assets/images/circle-flag/it.svg" alt="Italian"
                                    width="26" height="26" class="rounded-circle"> Italian
                            </div>
                        </div>
                    </ul>
                </div>
                <div class="dropdown pe-dropdown-mega">
                    <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn position-relative"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="ri-notification-4-line"></i>
                        <div class="icon-dot bg-danger"><span class="ping bg-danger"></span></div>
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
                                            <span
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">3</span>
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link px-2" data-bs-toggle="tab" href="#messages" role="tab"
                                            aria-selected="true">
                                            <span>Messages</span>
                                            <span
                                                class="h-20px w-20px ms-1 d-inline-flex align-items-center justify-content-center border fs-12 text-muted rounded-1">2</span>
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
                                        <div class="notification-panel" data-simplebar style="max-height: 324px;">
                                            <div class="notification">
                                                <h6 class="message mb-2">You have a new task</h6>
                                                <p class="fs-14 text-muted mb-0">Just now</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">New message from Naomi</h6>
                                                <p class="fs-14 text-muted mb-0">1 hour ago</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">Your role has been set to Admin</h6>
                                                <p class="fs-14 text-muted mb-0">3 days ago</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">New message from Robert</h6>
                                                <p class="fs-14 text-muted mb-0">2 weeks ago</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">Payment received from Jonathan</h6>
                                                <p class="fs-14 text-muted mb-0">3 days ago</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">New comment on your post</h6>
                                                <p class="fs-14 text-muted mb-0">5 hours ago</p>
                                            </div>
                                        </div>
                                        <div class="p-4 text-end">
                                            <a href="#!" class="text-body"><i
                                                    class="ri-check-double-line fs-5 text-primary"></i> Mark all as
                                                read</a>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="messages" role="tabpanel">
                                        <div class="notification-panel" data-simplebar style="max-height: 324px;">
                                            <div class="notification unread">
                                                <h6 class="message mb-2">New message from Emma</h6>
                                                <p class="fs-14 text-muted mb-0">5 minutes ago</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">Reminder: Meeting at 3 PM</h6>
                                                <p class="fs-14 text-muted mb-0">30 minutes ago</p>
                                            </div>
                                            <div class="notification unread">
                                                <h6 class="message mb-2">Newsletter from Marketing</h6>
                                                <p class="fs-14 text-muted mb-0">1 day ago</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">System message: Update complete</h6>
                                                <p class="fs-14 text-muted mb-0">4 days ago</p>
                                            </div>
                                            <div class="notification">
                                                <h6 class="message mb-2">Message archived from Clara</h6>
                                                <p class="fs-14 text-muted mb-0">1 week ago</p>
                                            </div>
                                        </div>
                                        <div class="p-4 text-end">
                                            <a href="#!" class="text-body"><i
                                                    class="ri-check-double-line fs-5 text-primary"></i> Mark all as
                                                read</a>
                                        </div>
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
                <div class="dropdown pe-dropdown-mega">
                    <button class="btn text-muted rounded-circle icon-btn fs-5 header-menu-btn position-relative"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Messages">
                        <i class="ri-shopping-cart-line"></i>
                        <div class="icon-dot bg-success"><span class="ping bg-success"></span></div>
                    </button>
                    <ul
                        class="dropdown-menu dropdown-menu-clickable dropdown-menu-end dropdown-mega-md header-dropdown-menu shadow-sm border p-0">
                        <div class="card mb-0 border-0">
                            <div class="card-header pb-2">
                                <h5 class="card-title">Cart Items</h5>
                                <a href="#!" class="fw-medium">See All</a>
                            </div>
                            <div class="card-body p-0">
                                <div data-simplebar style="max-height: 355px;">
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Margherita
                                                    Pizza</a>
                                                <p class="text-muted mb-0">Classic cheese & tomato</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="2" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹299</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Veg
                                                    Burger</a>
                                                <p class="text-muted mb-0">With crispy fries</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="1" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹149</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Paneer
                                                    Tikka</a>
                                                <p class="text-muted mb-0">Grilled cottage cheese</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="3" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹220</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Butter
                                                    Naan</a>
                                                <p class="text-muted mb-0">Soft & buttery flatbread</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="4" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹40</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Cold
                                                    Coffee</a>
                                                <p class="text-muted mb-0">Chilled & refreshing</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="2" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹99</h6>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-5 py-4 border-bottom">
                                        <div class="row align-items-center">
                                            <div class="col-7">
                                                <a href="#!" class="mb-2 text-body fw-medium d-block">Chocolate
                                                    Lava Cake</a>
                                                <p class="text-muted mb-0">Molten chocolate dessert</p>
                                            </div>
                                            <div class="col-3">
                                                <input type="number"
                                                    class="form-control form-control-sm text-center w-56px"
                                                    value="1" min="1">
                                            </div>
                                            <div class="col-2 text-end">
                                                <h6 class="mb-0">₹129</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="py-4 px-5 text-end">
                                <h6 class="mb-0">Total: ₹1495.00</h6>
                            </div>
                        </div>
                    </ul>
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
