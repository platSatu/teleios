@extends('layouts.dashboard')

@section('content')
    <div class="card wa-inbox-card">
        <div class="wa-inbox-shell">

            {{-- ============ LEFT: contacts ============ --}}
            <div class="wa-col wa-col-contacts">
                <div class="wa-contacts-toolbar">
                    <a href="{{ route('chat.connect-device.index') }}" class="wa-icon-btn" title="Kembali ke daftar device">
                        <i class="ri-arrow-left-line"></i>
                    </a>
                    <div class="wa-chat-filter">
                        <span>All Chats</span>
                        <span id="wa-chat-count" class="text-muted">(0)</span>
                    </div>
                    <button type="button" id="wa-new-chat-btn" class="wa-new-chat-btn" title="Mulai chat baru">
                        <i class="ri-add-line"></i> New
                    </button>
                </div>

                <div class="wa-search-wrap">
                    <i class="ri-search-line"></i>
                    <input type="text" id="wa-search-input" placeholder="Search chats...">
                </div>

                <div id="wa-chat-list" class="wa-chat-list">
                    <div class="wa-chat-list-empty" id="wa-chat-list-empty">Memuat percakapan...</div>
                </div>
            </div>

            {{-- ============ MIDDLE: thread ============ --}}
            <div class="wa-col wa-col-thread">
                <div id="wa-thread-header" class="wa-thread-header d-none">
                    <div class="wa-thread-header-left">
                        <button type="button" id="wa-thread-back-btn" class="wa-icon-btn wa-thread-back-btn" title="Kembali ke daftar chat">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                        <div id="wa-thread-avatar" class="wa-avatar-circle"></div>
                        <div class="overflow-hidden">
                            <h6 id="wa-thread-title" class="mb-0 text-truncate"></h6>
                            <small id="wa-thread-sub" class="text-muted"></small>
                        </div>
                    </div>
                    <div class="wa-thread-header-right">
                        <span id="wa-thread-presence-pill" class="wa-presence-pill wa-pill-offline">Offline</span>
                        <button type="button" class="wa-icon-btn" title="Cari di percakapan (segera hadir)"><i class="ri-search-line"></i></button>
                        <button type="button" id="wa-toggle-detail-btn" class="wa-icon-btn" title="Tampilkan/sembunyikan detail"><i class="ri-layout-right-line"></i></button>
                        <button type="button" class="wa-icon-btn" title="Menu lainnya (segera hadir)"><i class="ri-more-2-fill"></i></button>
                    </div>
                </div>

                <div id="wa-thread-empty" class="wa-thread-empty">
                    <i class="ri-chat-3-line"></i>
                    <p>Pilih percakapan di sebelah kiri untuk mulai chat.</p>
                </div>

                <div id="wa-thread-body" class="wa-thread-body d-none"></div>

                <div id="wa-attach-preview" class="wa-attach-preview">
                    <i class="ri-file-3-line" id="wa-attach-preview-icon"></i>
                    <span class="wa-attach-preview-name" id="wa-attach-preview-name"></span>
                    <button type="button" id="wa-attach-preview-cancel">Batal</button>
                </div>

                <form id="wa-send-form" class="wa-send-form d-none">
                    <input type="file" id="wa-attach-input" class="d-none" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt">
                    <div class="wa-send-icons">
                        <button type="button" class="wa-icon-btn" title="Emoji (segera hadir)"><i class="ri-emotion-line"></i></button>
                        <button type="button" class="wa-icon-btn" title="Balasan cepat (segera hadir)"><i class="ri-flashlight-line"></i></button>
                        <button type="button" class="wa-icon-btn" title="Template pesan (segera hadir)"><i class="ri-apps-2-line"></i></button>
                        <button type="button" id="wa-attach-btn" class="wa-icon-btn" title="Lampirkan file"><i class="ri-attachment-2"></i></button>
                    </div>
                    <input type="text" id="wa-send-input" class="wa-send-input" placeholder="Type a message... ( / for quick reply)" autocomplete="off">
                    <button type="submit" class="wa-send-btn" title="Kirim"><i class="ri-send-plane-2-fill"></i></button>
                </form>
            </div>

            {{-- ============ RIGHT: detail panel ============ --}}
            <div class="wa-col wa-col-detail" id="wa-detail-panel">
                <div id="wa-detail-empty" class="wa-detail-empty text-muted">
                    Pilih percakapan untuk melihat detail.
                </div>

                <div id="wa-detail-content" class="d-none">
                    <div class="wa-detail-header">
                        <div id="wa-detail-avatar" class="wa-avatar-circle wa-avatar-lg"></div>
                        <h6 id="wa-detail-name" class="mb-1"></h6>
                        <div class="text-muted small" id="wa-detail-phone"></div>
                    </div>

                    <div class="wa-detail-section">
                        <div class="wa-detail-section-title">
                            <span><i class="ri-user-add-line"></i> ASSIGNMENT</span>
                        </div>
                        <button type="button" class="wa-inert-btn" disabled title="Fitur assign ke tim/agent belum tersedia">+ Assign</button>
                    </div>

                    <div class="wa-detail-section">
                        <div class="wa-detail-section-title">
                            <span><i class="ri-price-tag-3-line"></i> LABELS</span>
                            <button type="button" class="wa-inert-link" disabled title="Fitur label belum tersedia">+ Add</button>
                        </div>
                        <div class="text-muted small fst-italic">No labels assigned</div>
                    </div>

                    <div class="wa-detail-section">
                        <div class="wa-detail-section-title">
                            <span><i class="ri-image-line"></i> MEDIA &amp; FILES</span>
                        </div>
                        <div class="wa-media-tabs">
                            <span class="wa-media-tab active">Photos</span>
                            <span class="wa-media-tab">Videos</span>
                            <span class="wa-media-tab">Docs</span>
                        </div>
                        <div class="text-muted small fst-italic">Belum ada media (pesan media belum didukung)</div>
                    </div>

                    <div class="wa-detail-section">
                        <div class="wa-detail-section-title">
                            <span><i class="ri-file-text-line"></i> NOTES</span>
                            <button type="button" class="wa-inert-link" disabled title="Fitur catatan belum tersedia">+ Add</button>
                        </div>
                        <div class="text-muted small fst-italic">Belum ada catatan.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        .wa-inbox-card { padding: 0; overflow: hidden; }
        .wa-inbox-shell { display: flex; height: 80vh; min-height: 520px; max-width: 100%; }

        .wa-col { display: flex; flex-direction: column; min-width: 0; }
        .wa-col-contacts { width: 320px; flex: 0 0 320px; border-right: 1px solid var(--bs-border-color, #e9ecef); background: #fff; }
        .wa-col-thread { flex: 1 1 auto; background: #f2e9de; }
        .wa-col-detail { width: 300px; flex: 0 0 300px; border-left: 1px solid var(--bs-border-color, #e9ecef); background: #fff; overflow-y: auto; padding: 18px; }
        .wa-col-detail.wa-hidden { display: none; }

        .wa-thread-back-btn { display: none; }

        @media (max-width: 1200px) {
            .wa-col-detail { display: none; }
        }
        @media (max-width: 860px) {
            .wa-col-contacts { width: 260px; flex-basis: 260px; }
        }

        /* --- mobile: one column at a time (list OR thread), like the --
           real WhatsApp Web/app do on narrow screens, instead of --
           squeezing 3 columns sideways where each ends up too --
           cramped to actually read/tap. --- */
        @media (max-width: 768px) {
            .wa-inbox-card { border-radius: 0; margin-left: -0.5rem; margin-right: -0.5rem; width: calc(100% + 1rem); }
            .wa-inbox-shell { height: calc(100vh - 170px); min-height: 380px; }

            .wa-col-contacts { width: 100%; flex-basis: 100%; border-right: none; }
            .wa-col-thread { display: none; width: 100%; flex-basis: 100%; }

            /* Toggled by JS: openChat() adds this on the shell so the
               thread takes over full-width and the list is tucked away
               (rather than both existing at 50% width each, which is
               unreadable on a phone), and the back button undoes it. */
            .wa-inbox-shell.wa-mobile-chat-open .wa-col-contacts { display: none; }
            .wa-inbox-shell.wa-mobile-chat-open .wa-col-thread { display: flex; }

            .wa-thread-back-btn { display: inline-flex; }

            .wa-thread-body { padding: 12px; }
            .wa-msg-bubble { max-width: 86%; }
            .wa-send-form { padding: 8px 10px; }

            /* The 3 "coming soon" placeholder buttons (emoji/quick
               reply/template — none of them do anything yet, see their
               disabled `title`) are the first things worth giving up to
               keep the actual message input usable at this width. */
            .wa-send-icons button:nth-child(1),
            .wa-send-icons button:nth-child(2),
            .wa-send-icons button:nth-child(3) { display: none; }
        }

        /* --- left column --- */
        .wa-contacts-toolbar { display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-bottom: 1px solid #f0f0f0; }
        .wa-chat-filter { flex: 1 1 auto; font-weight: 600; font-size: 0.92rem; display: flex; align-items: center; gap: 4px; }
        .wa-new-chat-btn { border: none; background: #16a34a; color: #fff; border-radius: 8px; padding: 6px 12px; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .wa-new-chat-btn:hover { background: #128a3e; }

        .wa-search-wrap { position: relative; padding: 10px 14px; border-bottom: 1px solid #f0f0f0; }
        .wa-search-wrap i { position: absolute; left: 26px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.95rem; }
        .wa-search-wrap input { width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 7px 12px 7px 32px; font-size: 0.85rem; background: #f9fafb; }
        .wa-search-wrap input:focus { outline: none; border-color: #16a34a; background: #fff; }

        .wa-chat-list { flex: 1 1 auto; overflow-y: auto; }
        .wa-chat-list-empty { padding: 32px 16px; text-align: center; color: #9ca3af; font-size: 0.85rem; }

        .wa-date-divider { padding: 8px 14px 4px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; color: #9ca3af; text-transform: uppercase; }

        .wa-chat-item { display: flex; align-items: flex-start; gap: 10px; width: 100%; text-align: left; background: transparent; border: none; border-bottom: 1px solid #f5f5f5; padding: 10px 14px; cursor: pointer; }
        .wa-chat-item:hover { background: #f9fafb; }
        .wa-chat-item.active { background: #eef7ee; }
        .wa-chat-item-body { flex: 1 1 auto; min-width: 0; }
        .wa-chat-item-top { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .wa-chat-item-name { font-weight: 600; font-size: 0.88rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wa-chat-item-time { font-size: 0.72rem; color: #9ca3af; flex-shrink: 0; }
        .wa-chat-item-bottom { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 2px; }
        .wa-chat-item-preview { font-size: 0.8rem; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wa-unread-badge { background: #16a34a; color: #fff; border-radius: 999px; min-width: 20px; height: 20px; padding: 0 6px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }

        /* --- avatars --- */
        .wa-avatar-circle { border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; overflow: hidden; }
        .wa-avatar-lg { width: 64px !important; height: 64px !important; font-size: 1.4rem; margin: 0 auto 10px; }

        /* --- middle column --- */
        .wa-thread-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; background: #fff; border-bottom: 1px solid #ece3d8; }
        .wa-thread-header.d-flex-visible { display: flex; }
        .wa-thread-header-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .wa-thread-header-right { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

        .wa-icon-btn { border: none; background: transparent; color: #6b7280; width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; }
        .wa-icon-btn:hover { background: #f1f5f9; color: #374151; }

        .wa-presence-pill { font-size: 0.72rem; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
        .wa-pill-online { background: #dcfce7; color: #15803d; }
        .wa-pill-typing { background: #dbeafe; color: #1d4ed8; }
        .wa-pill-offline { background: #f1f5f9; color: #6b7280; }

        .wa-thread-empty { flex: 1 1 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #9ca3af; gap: 8px; }
        .wa-thread-empty i { font-size: 2.5rem; }

        .wa-thread-body { flex: 1 1 auto; overflow-y: auto; padding: 18px; }
        .wa-msg-divider { text-align: center; margin: 10px 0; }
        .wa-msg-divider span { background: rgba(255,255,255,0.7); color: #6b7280; font-size: 0.72rem; padding: 3px 12px; border-radius: 999px; }

        .wa-msg-row { display: flex; margin-bottom: 8px; }
        .wa-msg-row.from-me { justify-content: flex-end; }
        .wa-msg-bubble { max-width: 68%; padding: 8px 12px; border-radius: 10px; font-size: 0.88rem; }
        .wa-msg-row.from-me .wa-msg-bubble { background: #dcf8c6; color: #1f2937; border-bottom-right-radius: 2px; }
        .wa-msg-row:not(.from-me) .wa-msg-bubble { background: #fff; color: #1f2937; border-bottom-left-radius: 2px; box-shadow: 0 1px 1px rgba(0,0,0,0.06); }
        .wa-msg-time { font-size: 0.68rem; color: #6b7280; text-align: right; margin-top: 3px; display: flex; align-items: center; justify-content: flex-end; gap: 3px; }
        .wa-msg-ack { font-size: 0.9rem; line-height: 1; color: #8696a0; }
        .wa-msg-ack.wa-msg-ack-read { color: #53bdeb; }

        .wa-msg-bubble-media { padding: 6px; }
        .wa-msg-bubble-media > img,
        .wa-msg-bubble-media > video,
        .wa-msg-bubble-media > audio,
        .wa-msg-bubble-media > a { display: block; margin-bottom: 4px; }
        .wa-msg-bubble-sticker { background: transparent !important; box-shadow: none !important; padding: 0; }
        .wa-msg-image { max-width: 260px; max-height: 320px; border-radius: 8px; object-fit: cover; }
        .wa-msg-video { max-width: 260px; border-radius: 8px; }
        .wa-msg-audio { width: 240px; }
        .wa-msg-sticker { width: 128px; height: 128px; object-fit: contain; }
        .wa-msg-document { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; background: rgba(0,0,0,0.05); color: inherit; text-decoration: none; font-size: 0.85rem; }
        .wa-msg-document i { font-size: 1.3rem; flex-shrink: 0; }

        .wa-attach-preview { display: none; align-items: center; gap: 10px; padding: 8px 14px; background: #f9fafb; border-top: 1px solid #ece3d8; font-size: 0.82rem; color: #374151; }
        .wa-attach-preview.show { display: flex; }
        .wa-attach-preview i { font-size: 1.2rem; color: #16a34a; flex-shrink: 0; }
        .wa-attach-preview .wa-attach-preview-name { flex: 1 1 auto; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wa-attach-preview button { border: none; background: transparent; color: #ef4444; font-size: 0.8rem; font-weight: 600; }

        .wa-send-form { display: flex; align-items: center; gap: 16px; padding: 12px 20px; background: #fff; border-top: 1px solid #ece3d8; }
        .wa-send-icons { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .wa-send-input { flex: 1 1 auto; border: 1px solid #e5e7eb; border-radius: 999px; padding: 10px 18px; font-size: 0.88rem; background: #f9fafb; min-width: 0; }
        .wa-send-input:focus { outline: none; border-color: #16a34a; background: #fff; }
        .wa-send-btn { border: none; background: #16a34a; color: #fff; width: 42px; height: 42px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; margin-left: 2px; }
        .wa-send-btn:hover { background: #128a3e; }

        /* --- right column --- */
        .wa-detail-empty { text-align: center; padding: 24px 8px; font-size: 0.85rem; }
        .wa-detail-header { text-align: center; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; margin-bottom: 16px; }
        .wa-detail-section { padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid #f5f5f5; }
        .wa-detail-section:last-child { border-bottom: none; }
        .wa-detail-section-title { display: flex; align-items: center; justify-content: space-between; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; color: #6b7280; text-transform: uppercase; margin-bottom: 10px; }
        .wa-detail-section-title i { margin-right: 4px; }

        .wa-inert-btn { width: 100%; border: 1px dashed #d1d5db; background: transparent; color: #9ca3af; border-radius: 8px; padding: 8px; font-size: 0.82rem; cursor: not-allowed; }
        .wa-inert-link { border: none; background: transparent; color: #9ca3af; font-size: 0.78rem; font-weight: 600; cursor: not-allowed; }

        .wa-media-tabs { display: flex; gap: 14px; margin-bottom: 10px; font-size: 0.8rem; }
        .wa-media-tab { color: #9ca3af; padding-bottom: 4px; }
        .wa-media-tab.active { color: #16a34a; font-weight: 600; border-bottom: 2px solid #16a34a; }
    </style>

    <script>
        (function () {
            const chatsUrl = @json(route('inbox.chats', ['device' => $deviceId]));
            const messagesUrlTemplate = @json(route('inbox.messages', ['device' => $deviceId, 'jid' => '__JID__']));
            const sendUrlTemplate = @json(route('inbox.send', ['device' => $deviceId, 'jid' => '__JID__']));
            const sendMediaUrlTemplate = @json(route('inbox.send-media', ['device' => $deviceId, 'jid' => '__JID__']));
            const mediaUrlTemplate = @json(route('inbox.media', ['device' => $deviceId, 'messageId' => '__MSGID__']));
            const presenceUrlTemplate = @json(route('inbox.presence', ['device' => $deviceId, 'jid' => '__JID__']));
            const csrfToken = @json(csrf_token());

            const chatListEl = document.getElementById('wa-chat-list');
            const chatListEmptyEl = document.getElementById('wa-chat-list-empty');
            const chatCountEl = document.getElementById('wa-chat-count');
            const searchInputEl = document.getElementById('wa-search-input');
            const newChatBtnEl = document.getElementById('wa-new-chat-btn');

            const threadHeaderEl = document.getElementById('wa-thread-header');
            const threadAvatarEl = document.getElementById('wa-thread-avatar');
            const threadTitleEl = document.getElementById('wa-thread-title');
            const threadSubEl = document.getElementById('wa-thread-sub');
            const threadPresencePillEl = document.getElementById('wa-thread-presence-pill');
            const threadEmptyEl = document.getElementById('wa-thread-empty');
            const threadBodyEl = document.getElementById('wa-thread-body');
            const sendFormEl = document.getElementById('wa-send-form');
            const sendInputEl = document.getElementById('wa-send-input');
            const attachBtnEl = document.getElementById('wa-attach-btn');
            const attachInputEl = document.getElementById('wa-attach-input');
            const attachPreviewEl = document.getElementById('wa-attach-preview');
            const attachPreviewNameEl = document.getElementById('wa-attach-preview-name');
            const attachPreviewIconEl = document.getElementById('wa-attach-preview-icon');
            const attachPreviewCancelEl = document.getElementById('wa-attach-preview-cancel');

            const detailPanelEl = document.getElementById('wa-detail-panel');
            const detailEmptyEl = document.getElementById('wa-detail-empty');
            const detailContentEl = document.getElementById('wa-detail-content');
            const detailAvatarEl = document.getElementById('wa-detail-avatar');
            const detailNameEl = document.getElementById('wa-detail-name');
            const detailPhoneEl = document.getElementById('wa-detail-phone');
            const toggleDetailBtnEl = document.getElementById('wa-toggle-detail-btn');
            const threadBackBtnEl = document.getElementById('wa-thread-back-btn');
            const inboxShellEl = document.querySelector('.wa-inbox-shell');

            let activeChatJid = null;
            let activeChat = null;
            let allChats = [];
            let searchTerm = '';
            let renderedChatsSignature = '';
            let detailVisible = true;

            // Message loading is a small incremental delta after the first
            // load, not a full re-fetch/re-render every poll (see
            // loadMessages/appendMessages below) — re-rendering the entire
            // thread from scratch every 3 seconds was the main cause of
            // chats feeling heavy, especially ones with a lot of
            // history-synced backlog.
            let messagesInitialized = false;
            let lastMessageId = 0;
            let lastRenderedDay = null;

            function urlForMedia(id) {
                return mediaUrlTemplate.replace('__MSGID__', encodeURIComponent(String(id)));
            }

            function urlFor(template, jid) {
                return template.replace('__JID__', encodeURIComponent(jid));
            }

            function fetchJson(url, options) {
                return fetch(url, Object.assign({ headers: { 'Accept': 'application/json' } }, options))
                    .then(function (res) { return res.json(); });
            }

            function timeLabel(iso) {
                if (!iso) return '';
                const date = new Date(iso);
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function dayKey(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                return d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
            }

            function dateGroupLabel(iso) {
                if (!iso) return 'Lainnya';
                const d = new Date(iso);
                const now = new Date();
                const startOfDay = function (x) { return new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime(); };
                const diffDays = Math.round((startOfDay(now) - startOfDay(d)) / 86400000);
                if (diffDays === 0) return 'Hari Ini';
                if (diffDays === 1) return 'Kemarin';
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
            }

            function initials(name) {
                return (name || '?').trim().charAt(0).toUpperCase();
            }

            // Deterministic color per contact so the list has the same
            // "varied colorful avatars" look as the reference design,
            // instead of every fallback avatar being the same flat grey.
            const AVATAR_COLORS = ['#F97316', '#14B8A6', '#8B5CF6', '#3B82F6', '#EC4899', '#F59E0B', '#10B981', '#6366F1'];
            function colorForName(name) {
                let hash = 0;
                const str = name || '?';
                for (let i = 0; i < str.length; i++) {
                    hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
                }
                return AVATAR_COLORS[hash % AVATAR_COLORS.length];
            }

            function phoneFromJid(jid) {
                if (!jid) return '';
                if (jid.indexOf('@g.us') !== -1) return 'Grup';
                return '+' + jid.split('@')[0];
            }

            // Populates an *existing* avatar container in place (clears and
            // rebuilds its contents/background) rather than swapping in a
            // brand new element — important for the thread header and
            // detail panel avatars, which are persistent elements reused
            // across every openChat() call. Replacing those wholesale
            // (e.g. via replaceWith) would leave our cached element
            // reference pointing at a detached node, silently breaking
            // avatar updates after the first chat was opened.
            function applyAvatar(container, chat, size) {
                container.innerHTML = '';
                container.style.width = size + 'px';
                container.style.height = size + 'px';

                if (chat.avatar_url) {
                    container.style.background = 'transparent';
                    const img = document.createElement('img');
                    img.src = chat.avatar_url;
                    img.alt = '';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '50%';
                    container.appendChild(img);
                    return;
                }

                const label = chat.name || chat.chat_jid;
                container.style.background = colorForName(label);
                container.style.fontSize = Math.max(12, Math.round(size * 0.4)) + 'px';
                container.textContent = initials(label);
            }

            // Used for chat list rows, which are always freshly created
            // (the whole list is rebuilt on every render), so a brand new
            // detached element per call is fine here.
            function makeAvatarEl(chat, size) {
                const el = document.createElement('div');
                el.className = 'wa-avatar-circle';
                applyAvatar(el, chat, size);
                return el;
            }

            function applyFilter(chats) {
                if (!searchTerm) return chats;
                const term = searchTerm.toLowerCase();
                return chats.filter(function (c) {
                    return (c.name || '').toLowerCase().indexOf(term) !== -1 ||
                        (c.chat_jid || '').toLowerCase().indexOf(term) !== -1;
                });
            }

            // The Go backend sometimes echoes the same chat_jid more than
            // once in one /chats response (e.g. one row per stored
            // message batch instead of one row per conversation), which
            // showed up as the same contact appearing twice in the list.
            // Collapse to one row per chat_jid here, keeping whichever
            // duplicate has the newest last_message_at and the highest
            // unread_count (so a real unread bump on either row isn't
            // silently dropped).
            function dedupeChats(chats) {
                const byJid = new Map();

                chats.forEach(function (c) {
                    if (!c || !c.chat_jid) return;
                    const existing = byJid.get(c.chat_jid);

                    if (!existing) {
                        byJid.set(c.chat_jid, c);
                        return;
                    }

                    const existingTime = Date.parse(existing.last_message_at) || 0;
                    const incomingTime = Date.parse(c.last_message_at) || 0;
                    const newer = incomingTime > existingTime ? c : existing;
                    const older = newer === c ? existing : c;

                    byJid.set(c.chat_jid, Object.assign({}, newer, {
                        unread_count: Math.max(Number(newer.unread_count) || 0, Number(older.unread_count) || 0),
                    }));
                });

                return Array.from(byJid.values());
            }

            function renderChatList(rawChats) {
                const chats = dedupeChats(rawChats);
                allChats = chats;
                chatCountEl.textContent = '(' + chats.length + ')';

                const filtered = applyFilter(chats);

                const signature = filtered.map(function (c) {
                    return c.chat_jid + ':' + c.last_message + ':' + c.unread_count + ':' + c.avatar_url + ':' + c.name;
                }).join('|') + '|active:' + activeChatJid + '|q:' + searchTerm;

                if (signature === renderedChatsSignature) return;
                renderedChatsSignature = signature;

                chatListEl.innerHTML = '';

                if (filtered.length === 0) {
                    chatListEmptyEl.textContent = searchTerm ? 'Tidak ada percakapan yang cocok.' : 'Belum ada percakapan.';
                    chatListEl.appendChild(chatListEmptyEl);
                    return;
                }

                let lastGroup = null;

                filtered.forEach(function (chat) {
                    const group = dateGroupLabel(chat.last_message_at);
                    if (group !== lastGroup) {
                        lastGroup = group;
                        const divider = document.createElement('div');
                        divider.className = 'wa-date-divider';
                        divider.textContent = group;
                        chatListEl.appendChild(divider);
                    }

                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'wa-chat-item' + (chat.chat_jid === activeChatJid ? ' active' : '');

                    const body = document.createElement('div');
                    body.className = 'wa-chat-item-body';

                    const top = document.createElement('div');
                    top.className = 'wa-chat-item-top';
                    const name = document.createElement('span');
                    name.className = 'wa-chat-item-name';
                    name.textContent = chat.name || chat.chat_jid;
                    const time = document.createElement('span');
                    time.className = 'wa-chat-item-time';
                    time.textContent = timeLabel(chat.last_message_at);
                    top.appendChild(name);
                    top.appendChild(time);

                    const bottom = document.createElement('div');
                    bottom.className = 'wa-chat-item-bottom';
                    const preview = document.createElement('span');
                    preview.className = 'wa-chat-item-preview';
                    preview.textContent = chat.last_message || '';
                    bottom.appendChild(preview);

                    if (chat.unread_count > 0) {
                        const badge = document.createElement('span');
                        badge.className = 'wa-unread-badge';
                        badge.textContent = chat.unread_count;
                        bottom.appendChild(badge);
                    }

                    body.appendChild(top);
                    body.appendChild(bottom);

                    item.appendChild(makeAvatarEl(chat, 42));
                    item.appendChild(body);
                    item.addEventListener('click', function () {
                        openChat(chat);
                    });

                    chatListEl.appendChild(item);
                });
            }

            // Normalizes whatever shape the Go backend sends a delivery
            // state in ('status' string, numeric whatsmeow-style 'ack',
            // or 'message_status') into one of: pending | sent |
            // delivered | read. Any from_me message that's already in
            // history was, at minimum, sent — so that's the fallback
            // rather than showing nothing.
            function ackState(msg) {
                if (typeof msg.status === 'string' && msg.status) {
                    return msg.status.toLowerCase();
                }
                if (typeof msg.message_status === 'string' && msg.message_status) {
                    return msg.message_status.toLowerCase();
                }
                if (typeof msg.ack !== 'undefined' && msg.ack !== null) {
                    const n = Number(msg.ack);
                    if (n <= 0) return 'pending';
                    if (n === 1) return 'sent';
                    if (n === 2) return 'delivered';
                    if (n >= 3) return 'read';
                }
                return 'sent';
            }

            function ackIcon(state) {
                const icon = document.createElement('i');
                icon.className = 'wa-msg-ack';

                if (state === 'pending') {
                    icon.classList.add('ri-time-line');
                } else if (state === 'read' || state === 'played') {
                    icon.classList.add('ri-check-double-line', 'wa-msg-ack-read');
                } else if (state === 'delivered') {
                    icon.classList.add('ri-check-double-line');
                } else {
                    icon.classList.add('ri-check-line');
                }

                return icon;
            }

            // Builds the image/video/audio/document/sticker element for a
            // media message, or null for a plain text one. msg.media_url
            // is just a "this message has stored media" flag from the Go
            // backend (it's Go's own internal API path) — the actual
            // <img>/<video>/... src is always our own Laravel proxy route
            // (urlForMedia), never msg.media_url directly, since the
            // browser can't reach the Go API's host/port or auth header.
            function mediaElement(msg) {
                if (!msg.media_url) return null;

                const url = urlForMedia(msg.id);

                if (msg.message_type === 'image' || msg.message_type === 'sticker') {
                    const img = document.createElement('img');
                    img.src = url;
                    img.loading = 'lazy';
                    img.alt = msg.file_name || (msg.message_type === 'sticker' ? 'Stiker' : 'Gambar');
                    img.className = msg.message_type === 'sticker' ? 'wa-msg-sticker' : 'wa-msg-image';
                    return img;
                }

                if (msg.message_type === 'video') {
                    const video = document.createElement('video');
                    video.src = url;
                    video.controls = true;
                    video.className = 'wa-msg-video';
                    return video;
                }

                if (msg.message_type === 'audio') {
                    const audio = document.createElement('audio');
                    audio.src = url;
                    audio.controls = true;
                    audio.className = 'wa-msg-audio';
                    return audio;
                }

                if (msg.message_type === 'document') {
                    const link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'wa-msg-document';

                    const icon = document.createElement('i');
                    icon.className = 'ri-file-3-line';
                    const name = document.createElement('span');
                    name.textContent = msg.file_name || 'Dokumen';

                    link.appendChild(icon);
                    link.appendChild(name);
                    return link;
                }

                return null;
            }

            function messageBubble(msg) {
                const row = document.createElement('div');
                row.className = 'wa-msg-row' + (msg.from_me ? ' from-me' : '');

                const bubble = document.createElement('div');
                bubble.className = 'wa-msg-bubble';

                const media = mediaElement(msg);
                if (media) {
                    bubble.classList.add('wa-msg-bubble-media');
                    if (msg.message_type === 'sticker') {
                        bubble.classList.add('wa-msg-bubble-sticker');
                    }
                    bubble.appendChild(media);
                }

                // A media message with no caption skips the text div
                // entirely, so a bare photo isn't followed by an empty
                // line — but a plain text message (no media at all)
                // always gets one, even if body somehow came back empty.
                if (msg.body || !media) {
                    const text = document.createElement('div');
                    text.textContent = msg.body;
                    bubble.appendChild(text);
                }

                const time = document.createElement('div');
                time.className = 'wa-msg-time';
                const timeText = document.createElement('span');
                timeText.textContent = timeLabel(msg.sent_at);
                time.appendChild(timeText);

                // Ticks only make sense for messages we sent — incoming
                // messages don't carry a "did they receive it" state.
                if (msg.from_me) {
                    time.appendChild(ackIcon(ackState(msg)));
                }

                bubble.appendChild(time);
                row.appendChild(bubble);
                return row;
            }

            function dayDivider(iso) {
                const wrap = document.createElement('div');
                wrap.className = 'wa-msg-divider';
                const span = document.createElement('span');
                span.textContent = dateGroupLabel(iso);
                wrap.appendChild(span);
                return wrap;
            }

            // Full replace: used only for the first load of a newly opened
            // chat.
            function renderMessagesFull(messages) {
                threadBodyEl.innerHTML = '';
                lastMessageId = 0;
                lastRenderedDay = null;

                messages.forEach(function (msg) {
                    const key = dayKey(msg.sent_at);
                    if (key !== lastRenderedDay) {
                        lastRenderedDay = key;
                        threadBodyEl.appendChild(dayDivider(msg.sent_at));
                    }
                    threadBodyEl.appendChild(messageBubble(msg));
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });

                threadBodyEl.scrollTop = threadBodyEl.scrollHeight;
            }

            // Incremental append: used for every poll after the first load,
            // and for a message we just sent — avoids rebuilding (and
            // losing scroll position in) the whole thread just to show one
            // or two new messages.
            function appendMessages(messages) {
                if (messages.length === 0) return;

                const wasAtBottom = threadBodyEl.scrollTop + threadBodyEl.clientHeight >= threadBodyEl.scrollHeight - 20;

                messages.forEach(function (msg) {
                    const key = dayKey(msg.sent_at);
                    if (key !== lastRenderedDay) {
                        lastRenderedDay = key;
                        threadBodyEl.appendChild(dayDivider(msg.sent_at));
                    }
                    threadBodyEl.appendChild(messageBubble(msg));
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });

                if (wasAtBottom) {
                    threadBodyEl.scrollTop = threadBodyEl.scrollHeight;
                }
            }

            function renderPresence(presence) {
                if (presence.state === 'typing') {
                    threadSubEl.textContent = 'mengetik...';
                    threadPresencePillEl.textContent = 'Typing';
                    threadPresencePillEl.className = 'wa-presence-pill wa-pill-typing';
                } else if (presence.state === 'online') {
                    threadSubEl.textContent = 'online';
                    threadPresencePillEl.textContent = 'Online';
                    threadPresencePillEl.className = 'wa-presence-pill wa-pill-online';
                } else {
                    threadSubEl.textContent = presence.last_seen ? ('terakhir dilihat ' + timeLabel(presence.last_seen)) : '';
                    threadPresencePillEl.textContent = 'Offline';
                    threadPresencePillEl.className = 'wa-presence-pill wa-pill-offline';
                }
            }

            function renderDetail(chat) {
                detailEmptyEl.classList.add('d-none');
                detailContentEl.classList.remove('d-none');

                applyAvatar(detailAvatarEl, chat, 64);

                detailNameEl.textContent = chat.name || chat.chat_jid;
                detailPhoneEl.textContent = phoneFromJid(chat.chat_jid);
            }

            function openChat(chat) {
                activeChatJid = chat.chat_jid;
                activeChat = chat;
                messagesInitialized = false; // force a full (re)load for the new chat
                lastMessageId = 0;
                renderedChatsSignature = ''; // force chat list re-render to highlight selection
                clearAttachPreview(); // a pending attachment shouldn't follow you into a different chat

                // No-op above the 768px breakpoint (see the CSS) — below
                // it, this swaps the list out for the thread taking the
                // full width instead of both being squeezed side by side.
                if (inboxShellEl) inboxShellEl.classList.add('wa-mobile-chat-open');

                threadEmptyEl.classList.add('d-none');
                threadHeaderEl.classList.remove('d-none');
                threadHeaderEl.style.display = 'flex';
                threadBodyEl.classList.remove('d-none');
                sendFormEl.classList.remove('d-none');
                threadTitleEl.textContent = chat.name || chat.chat_jid;
                threadSubEl.textContent = '';

                applyAvatar(threadAvatarEl, chat, 40);

                renderDetail(chat);

                loadMessages();
                loadPresence();
                // Opening a chat clears its unread count server-side; pull
                // the chat list again right away instead of waiting for the
                // next scheduled poll, so the badge disappears immediately.
                loadChats();
            }

            function loadMessages() {
                if (!activeChatJid) return;

                const requestedChatJid = activeChatJid;
                const isInitialLoad = !messagesInitialized;
                let url = urlFor(messagesUrlTemplate, requestedChatJid);
                if (!isInitialLoad) {
                    url += '?after_id=' + lastMessageId;
                }

                fetchJson(url).then(function (data) {
                    // Bail out if the user switched to a different chat (or
                    // closed it) while this request was in flight — applying
                    // a stale response would render the wrong chat's
                    // messages into the currently open thread.
                    if (activeChatJid !== requestedChatJid) return;

                    const messages = data.messages || [];
                    if (isInitialLoad) {
                        renderMessagesFull(messages);
                        messagesInitialized = true;
                    } else {
                        appendMessages(messages);
                    }
                });
            }

            function loadPresence() {
                if (!activeChatJid) return;

                fetchJson(urlFor(presenceUrlTemplate, activeChatJid))
                    .then(function (data) {
                        if (activeChatJid) renderPresence(data);
                    });
            }

            function loadChats() {
                fetchJson(chatsUrl).then(function (data) {
                    renderChatList(data.chats || []);
                });
            }

            searchInputEl.addEventListener('input', function () {
                searchTerm = searchInputEl.value.trim();
                renderedChatsSignature = ''; // force re-render with the new filter
                renderChatList(allChats);
            });

            newChatBtnEl.addEventListener('click', function () {
                const raw = prompt('Masukkan nomor WhatsApp tujuan (contoh: 6281234567890):');
                if (!raw) return;

                let digits = raw.replace(/\D/g, '');
                if (digits.charAt(0) === '0') digits = '62' + digits.slice(1);

                if (!digits) {
                    alert('Nomor tidak valid.');
                    return;
                }

                openChat({ chat_jid: digits + '@s.whatsapp.net', name: '+' + digits, avatar_url: '', last_message_at: null });
            });

            toggleDetailBtnEl.addEventListener('click', function () {
                detailVisible = !detailVisible;
                detailPanelEl.classList.toggle('wa-hidden', !detailVisible);
            });

            if (threadBackBtnEl) {
                threadBackBtnEl.addEventListener('click', function () {
                    if (inboxShellEl) inboxShellEl.classList.remove('wa-mobile-chat-open');
                });
            }

            // --- attachments ---
            let pendingFile = null;

            function attachIconClassFor(file) {
                if (file.type.indexOf('image/') === 0) return 'ri-image-line';
                if (file.type.indexOf('video/') === 0) return 'ri-video-line';
                if (file.type.indexOf('audio/') === 0) return 'ri-mic-line';
                return 'ri-file-3-line';
            }

            function showAttachPreview(file) {
                pendingFile = file;
                if (attachPreviewIconEl) attachPreviewIconEl.className = attachIconClassFor(file);
                if (attachPreviewNameEl) attachPreviewNameEl.textContent = file.name;
                if (attachPreviewEl) attachPreviewEl.classList.add('show');
                sendInputEl.placeholder = 'Tambahkan keterangan (opsional)...';
                sendInputEl.focus();
            }

            function clearAttachPreview() {
                pendingFile = null;
                if (attachInputEl) attachInputEl.value = '';
                if (attachPreviewEl) attachPreviewEl.classList.remove('show');
                sendInputEl.placeholder = 'Type a message... ( / for quick reply)';
            }

            if (attachBtnEl && attachInputEl) {
                attachBtnEl.addEventListener('click', function () {
                    attachInputEl.click();
                });

                attachInputEl.addEventListener('change', function () {
                    const file = attachInputEl.files && attachInputEl.files[0];
                    if (!file) return;

                    // Matches WaMediaController's own cap on the Go side —
                    // reject an oversized file here instead of uploading
                    // the whole thing only to have Go say no.
                    if (file.size > 32 * 1024 * 1024) {
                        alert('File terlalu besar (maksimal 32MB).');
                        attachInputEl.value = '';
                        return;
                    }

                    showAttachPreview(file);
                });
            }

            if (attachPreviewCancelEl) {
                attachPreviewCancelEl.addEventListener('click', clearAttachPreview);
            }

            sendFormEl.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!activeChatJid) return;

                if (pendingFile) {
                    const file = pendingFile;
                    const caption = sendInputEl.value.trim();
                    const formData = new FormData();
                    formData.append('file', file);
                    if (caption) formData.append('caption', caption);

                    sendInputEl.disabled = true;
                    if (attachBtnEl) attachBtnEl.disabled = true;

                    fetchJson(urlFor(sendMediaUrlTemplate, activeChatJid), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    }).then(function (data) {
                        if (!data.error) {
                            clearAttachPreview();
                            sendInputEl.value = '';
                            if (data.message && messagesInitialized) {
                                appendMessages([data.message]);
                            } else {
                                loadMessages();
                            }
                            loadChats();
                        } else {
                            alert(data.error || 'Gagal mengirim media.');
                        }
                    }).finally(function () {
                        sendInputEl.disabled = false;
                        if (attachBtnEl) attachBtnEl.disabled = false;
                        sendInputEl.focus();
                    });
                    return;
                }

                const body = sendInputEl.value.trim();
                if (!body) return;

                sendInputEl.disabled = true;

                fetchJson(urlFor(sendUrlTemplate, activeChatJid), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ body: body }),
                }).then(function (data) {
                    if (!data.error) {
                        sendInputEl.value = '';
                        // Show the message right away using the response we
                        // already have, instead of waiting for the next
                        // 3-second poll to pick it up — this is what made
                        // sending feel delayed.
                        if (data.message && messagesInitialized) {
                            appendMessages([data.message]);
                        } else {
                            loadMessages();
                        }
                        loadChats();
                    }
                }).finally(function () {
                    sendInputEl.disabled = false;
                    sendInputEl.focus();
                });
            });

            loadChats();
            setInterval(loadChats, 6000);
            setInterval(loadMessages, 3000);
            setInterval(loadPresence, 3000);
        })();
    </script>
@endsection
