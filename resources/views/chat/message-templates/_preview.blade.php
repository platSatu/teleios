<div class="card border-0 shadow-sm tpl-preview-card">
    <div class="card-body d-flex flex-column align-items-center">
        <h6 class="text-muted text-uppercase small mb-3 align-self-start">Live Preview</h6>

        <div class="tpl-phone-mockup">
            <div class="tpl-phone-notch"></div>
            <div class="tpl-phone-statusbar">
                <span>9:41</span>
                <span class="tpl-phone-statusbar-icons"><i class="ri-signal-wifi-line"></i> <i class="ri-battery-2-line"></i></span>
            </div>
            <div class="tpl-phone-header">
                <i class="ri-arrow-left-line"></i>
                <div class="tpl-phone-avatar"><i class="ri-building-2-line"></i></div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold small text-truncate">Nama Perusahaan</div>
                    <div class="text-white-50" style="font-size:11px">online</div>
                </div>
                <i class="ri-vidicon-line"></i>
                <i class="ri-phone-line"></i>
                <i class="ri-more-2-fill"></i>
            </div>
            <div class="tpl-phone-body">
                <div class="tpl-bubble">
                    <div id="tpl-preview-attachment" class="tpl-bubble-attachment" style="display:none;"></div>
                    <div id="tpl-preview-header" class="tpl-bubble-header"></div>
                    <div id="tpl-preview-body" class="tpl-bubble-body">Isi pesan akan muncul di sini...</div>
                    <div id="tpl-preview-link" class="tpl-bubble-link" style="display:none;"></div>
                    <div id="tpl-preview-footer" class="tpl-bubble-footer"></div>
                    <div class="tpl-bubble-time">12:00 <i class="ri-check-double-line text-info"></i></div>
                </div>
                <div id="tpl-preview-buttons" class="tpl-bubble-buttons"></div>
            </div>
            <div class="tpl-phone-composer">
                <div class="tpl-phone-composer-input"><i class="ri-emotion-line"></i> <span class="text-muted small">Ketik pesan</span></div>
                <div class="tpl-phone-composer-mic"><i class="ri-mic-line"></i></div>
            </div>
            <div class="tpl-phone-home-indicator"></div>
        </div>
    </div>
</div>

<style>
    .tpl-preview-card { position: sticky; top: 1rem; }

    .tpl-phone-mockup {
        width: 100%;
        max-width: 340px;
        max-height: 560px;
        aspect-ratio: 9 / 16;
        border: 10px solid #111;
        border-radius: 2.5rem;
        overflow: hidden;
        background: #e5ddd5;
        box-shadow: 0 12px 32px rgba(0,0,0,.2);
        display: flex;
        flex-direction: column;
        position: relative;
        margin: 0 auto;
    }
    .tpl-phone-notch {
        position: absolute;
        top: 0; left: 50%; transform: translateX(-50%);
        width: 40%; height: 20px;
        background: #111;
        border-bottom-left-radius: 14px;
        border-bottom-right-radius: 14px;
        z-index: 3;
    }
    .tpl-phone-statusbar {
        background: #075e54;
        color: #fff;
        font-size: 11px;
        padding: .4rem .9rem .1rem;
        display: flex;
        justify-content: space-between;
    }
    .tpl-phone-statusbar-icons i { margin-left: 4px; }
    .tpl-phone-header {
        background: #075e54;
        color: #fff;
        padding: .5rem .75rem;
        display: flex;
        align-items: center;
        gap: .55rem;
        flex-shrink: 0;
    }
    .tpl-phone-header > i { font-size: 1.1rem; opacity: .9; }
    .tpl-phone-avatar {
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .tpl-phone-body {
        flex: 1 1 auto;
        padding: .85rem;
        overflow-y: auto;
        background-image:
            radial-gradient(rgba(255,255,255,.5) 1px, transparent 1px);
        background-size: 14px 14px;
        background-color: #e5ddd5;
    }
    .tpl-bubble {
        background: #fff;
        border-radius: .6rem;
        padding: .55rem .65rem .35rem;
        max-width: 94%;
        box-shadow: 0 1px 1px rgba(0,0,0,.1);
        font-size: 13.5px;
        word-break: break-word;
        animation: tpl-bubble-in .18s ease-out;
    }
    @keyframes tpl-bubble-in {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .tpl-bubble-header { font-weight: 700; margin-bottom: .2rem; }
    .tpl-bubble-header:empty, .tpl-bubble-footer:empty { display: none; }
    .tpl-bubble-body { white-space: pre-wrap; color: #111; }
    .tpl-bubble-link {
        margin-top: .35rem;
        color: #039be5;
        font-size: 12.5px;
        word-break: break-all;
        display: flex;
        align-items: center;
        gap: .3rem;
    }
    .tpl-bubble-attachment {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: #f0f2f5;
        border-radius: .4rem;
        padding: .5rem;
        margin-bottom: .4rem;
        font-size: 12.5px;
        color: #444;
    }
    .tpl-bubble-attachment i { font-size: 1.3rem; color: #667781; }
    .tpl-bubble-footer { color: #667781; font-size: 12px; margin-top: .3rem; }
    .tpl-bubble-time {
        text-align: right;
        color: #667781;
        font-size: 10.5px;
        margin-top: .2rem;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 3px;
    }
    .tpl-bubble-buttons { max-width: 94%; }
    .tpl-bubble-buttons .tpl-preview-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        background: #fff;
        color: #00a5f4;
        font-size: 13px;
        font-weight: 600;
        padding: .45rem;
        margin-top: 3px;
        border-radius: .35rem;
        box-shadow: 0 1px 1px rgba(0,0,0,.1);
    }
    .tpl-phone-composer {
        flex-shrink: 0;
        background: #f0f0f0;
        padding: .5rem .6rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .tpl-phone-composer-input {
        flex: 1 1 auto;
        background: #fff;
        border-radius: 18px;
        padding: .4rem .8rem;
        display: flex;
        align-items: center;
        gap: .4rem;
        color: #8696a0;
    }
    .tpl-phone-composer-mic {
        width: 34px; height: 34px; border-radius: 50%;
        background: #075e54; color: #fff;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .tpl-phone-home-indicator {
        flex-shrink: 0;
        height: 16px;
        display: flex; align-items: center; justify-content: center;
        background: #f0f0f0;
    }
    .tpl-phone-home-indicator::after {
        content: '';
        width: 100px; height: 4px;
        background: #111;
        border-radius: 4px;
        opacity: .6;
    }
</style>

<script>
(function () {
    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function applyVariables(text, variables) {
        return (text || '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, function (match, name) {
            const value = variables && variables[name];
            return value ? value : match;
        });
    }

    // WhatsApp markdown -> safe HTML. Escapes first, THEN applies formatting
    // tokens, so no user-entered text can ever inject a real tag.
    function renderMarkdown(text) {
        let safe = escapeHtml(text);

        // Protect any un-substituted double-curly-brace variable
        // placeholder from the token replacements below (write-up here
        // keeps the braces spaced apart -- "{ {" / "} }" -- on purpose,
        // since two of them written back-to-back would make Blade try
        // to compile this very comment as a PHP echo tag).
        // A placeholder is still in that raw "{ {name} }" form when no
        // "Contoh Nilai Variabel" was filled in for it yet --
        // applyVariables() leaves it untouched in that case. Variable
        // names routinely contain "_" (nama_pengajar, rentang_tanggal,
        // ...), and without this guard that underscore gets paired up
        // with the next one found ANYWHERE later in the message --
        // across other placeholders too -- and both get mangled into
        // italic, chopping the opening/closing braces off along the
        // way. The real WhatsApp message never has this problem
        // (variables are substituted with real values, e.g. an actual
        // name, before sending -- see WaMessageTemplate::composedMessage()
        // callers), so this only needs to protect the PREVIEW.
        const placeholders = [];
        safe = safe.replace(/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/g, function (match) {
            placeholders.push(match);
            return '\u0000' + (placeholders.length - 1) + '\u0000';
        });

        safe = safe.replace(/```([^`]+)```/g, '<code>$1</code>');
        safe = safe.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
        safe = safe.replace(/_([^_]+)_/g, '<em>$1</em>');
        safe = safe.replace(/~([^~]+)~/g, '<s>$1</s>');

        safe = safe.replace(/\u0000(\d+)\u0000/g, function (match, idx) {
            return placeholders[Number(idx)];
        });

        return safe.replace(/\n/g, '<br>');
    }

    const headerOut = document.getElementById('tpl-preview-header');
    const bodyOut = document.getElementById('tpl-preview-body');
    const footerOut = document.getElementById('tpl-preview-footer');
    const linkOut = document.getElementById('tpl-preview-link');
    const attachmentOut = document.getElementById('tpl-preview-attachment');
    const buttonsOut = document.getElementById('tpl-preview-buttons');

    function iconFor(type) {
        return type === 'phone' ? 'ri-phone-line' : 'ri-external-link-line';
    }

    // Attachment field is a plain <input type="file"> — there's no live
    // "state" to poll for it the way text fields have, so this listens
    // for its own change event once, on top of whatever __tplGetState()
    // already reports for everything else.
    let attachmentLabel = '';
    const attachmentInput = document.querySelector('input[name="attachment"]');
    const existingAttachmentName = document.getElementById('tpl-existing-attachment')
        ? document.getElementById('tpl-existing-attachment').querySelector('a').textContent.trim()
        : '';
    attachmentLabel = existingAttachmentName;
    if (attachmentInput) {
        attachmentInput.addEventListener('change', function () {
            attachmentLabel = attachmentInput.files[0] ? attachmentInput.files[0].name : existingAttachmentName;
            window.__tplUpdatePreview && window.__tplUpdatePreview();
        });
    }

    // Turns one button into the exact text line
    // App\Models\WaMessageTemplate::composedMessage() sends it as — g_backend's
    // whatsmeow integration has no real "tappable button bar" message type,
    // so every button is really just flattened into a plain text line with
    // an emoji instead (see that method's docblock). Kept in sync with the
    // PHP `match` there by hand since the preview here runs client-side.
    function buttonLine(btn) {
        const label = (btn.label || '').trim();
        if (!label) return null;
        const value = (btn.value || '').trim();

        if (btn.type === 'phone') return value ? ('📞 ' + label + ': ' + value) : ('📞 ' + label);
        if (btn.type === 'url') return value ? ('🔗 ' + label + ': ' + value) : ('🔗 ' + label);
        return '• ' + label;
    }

    window.__tplUpdatePreview = function () {
        if (!window.__tplGetState) return;
        const state = window.__tplGetState();

        // Everything below used to render in its own styled zone (bold
        // header line, blue clickable link row, a separate green button
        // bar below the bubble) — which looked right in this mockup but
        // doesn't match what actually arrives on WhatsApp: g_backend only
        // ever sends ONE plain text message, built by flattening header +
        // body + link + buttons(-as-text) + footer together with blank
        // lines between them (WaMessageTemplate::composedMessage(), the
        // exact function every send path calls). Composing that same
        // single block here — instead of 4 separately-styled pieces — is
        // what makes this preview actually match the WhatsApp bubble the
        // recipient gets.
        const showLink = (state.contentType === 'text_link' || state.contentType === 'text_link_file') && state.link;

        const parts = [
            applyVariables(state.header, state.variables).trim(),
            applyVariables(state.body, state.variables).trim(),
            showLink ? state.link.trim() : '',
            (state.buttons || []).map(buttonLine).filter(Boolean).join('\n'),
            applyVariables(state.footer, state.variables).trim(),
        ].filter(function (part) { return part !== ''; });

        const composed = parts.join('\n\n');
        bodyOut.innerHTML = composed ? renderMarkdown(composed) : 'Isi pesan akan muncul di sini...';

        // These no longer get their own content — left empty (not just
        // hidden) so the :empty CSS rules already on .tpl-bubble-header/
        // -footer keep collapsing their spacing too.
        headerOut.innerHTML = '';
        footerOut.innerHTML = '';
        linkOut.style.display = 'none';
        buttonsOut.style.display = 'none';
        buttonsOut.innerHTML = '';

        const showAttachment = state.contentType === 'text_link_file' && attachmentLabel;
        attachmentOut.style.display = showAttachment ? '' : 'none';
        if (showAttachment) {
            attachmentOut.innerHTML = '<i class="ri-file-3-line"></i> <span class="text-truncate">' + escapeHtml(attachmentLabel) + '</span>';
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.__tplUpdatePreview && window.__tplUpdatePreview();
    });
    // In case this script runs after DOMContentLoaded already fired.
    if (document.readyState !== 'loading') {
        window.__tplUpdatePreview && window.__tplUpdatePreview();
    }
})();
</script>
