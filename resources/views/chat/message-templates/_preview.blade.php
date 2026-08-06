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
        safe = safe.replace(/```([^`]+)```/g, '<code>$1</code>');
        safe = safe.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
        safe = safe.replace(/_([^_]+)_/g, '<em>$1</em>');
        safe = safe.replace(/~([^~]+)~/g, '<s>$1</s>');
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

    window.__tplUpdatePreview = function () {
        if (!window.__tplGetState) return;
        const state = window.__tplGetState();

        headerOut.innerHTML = renderMarkdown(applyVariables(state.header, state.variables));
        bodyOut.innerHTML = state.body
            ? renderMarkdown(applyVariables(state.body, state.variables))
            : 'Isi pesan akan muncul di sini...';
        footerOut.innerHTML = renderMarkdown(applyVariables(state.footer, state.variables));

        const showLink = (state.contentType === 'text_link' || state.contentType === 'text_link_file') && state.link;
        linkOut.style.display = showLink ? '' : 'none';
        if (showLink) {
            linkOut.innerHTML = '<i class="ri-link"></i> ' + escapeHtml(state.link);
        }

        const showAttachment = state.contentType === 'text_link_file' && attachmentLabel;
        attachmentOut.style.display = showAttachment ? '' : 'none';
        if (showAttachment) {
            attachmentOut.innerHTML = '<i class="ri-file-3-line"></i> <span class="text-truncate">' + escapeHtml(attachmentLabel) + '</span>';
        }

        buttonsOut.innerHTML = '';
        (state.buttons || []).forEach(function (btn) {
            if (!btn.label) return;
            const el = document.createElement('div');
            el.className = 'tpl-preview-btn';
            el.innerHTML = '<i class="' + iconFor(btn.type) + '"></i> ' + escapeHtml(btn.label);
            buttonsOut.appendChild(el);
        });
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
