/*!
 * Live Chat Widget (teleios) -- pasang di website mana pun:
 *   <script src="https://DOMAIN-TELEIOS/widget.js" data-key="wgt_xxx" async></script>
 * Hanya membuat tombol + iframe. Semua chat berjalan di dalam iframe
 * (domain teleios), jadi tidak bentrok dengan CSS/JS website ini dan
 * website ini tidak bisa membaca isi chat.
 */
(function () {
    var script = document.currentScript || document.querySelector('script[data-key][src*="widget.js"]');
    if (!script || window.__teleiosChatWidget) return;
    window.__teleiosChatWidget = true;

    var key = script.getAttribute('data-key');
    var base = script.src.replace(/\/widget\.js.*$/, '');
    var color = script.getAttribute('data-color') || '#2563eb';
    var side = script.getAttribute('data-position') === 'left' ? 'left' : 'right';
    if (!key) return;

    var button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', 'Buka chat');
    button.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2z"/></svg>';
    button.style.cssText = 'position:fixed;bottom:20px;' + side + ':20px;width:58px;height:58px;border-radius:50%;border:0;cursor:pointer;z-index:2147483646;box-shadow:0 6px 20px rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;background:' + color;

    var frame = null;

    // HP (layar kecil): jendela chat layar penuh supaya kolom pesan & tombol
    // selalu terlihat. Desktop: jendela mengambang di pojok.
    function isMobile() {
        return window.matchMedia('(max-width: 480px)').matches;
    }

    function layout() {
        if (!frame) return;
        frame.style.cssText = isMobile()
            ? 'position:fixed;inset:0;width:100%;height:100%;height:100dvh;border:0;border-radius:0;z-index:2147483647;background:#fff'
            : 'position:fixed;bottom:90px;' + side + ':20px;width:370px;max-width:calc(100vw - 24px);height:560px;max-height:calc(100vh - 110px);border:0;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.25);z-index:2147483647;background:#fff';
    }

    function setOpen(isOpen) {
        frame.style.display = isOpen ? 'block' : 'none';
        // Di HP tombol bulat disembunyikan selama chat terbuka (tutup lewat tombol x).
        button.style.display = isOpen && isMobile() ? 'none' : 'flex';
    }

    function open() {
        if (!frame) {
            frame = document.createElement('iframe');
            frame.src = base + '/chat-widget/' + encodeURIComponent(key) + '?page=' + encodeURIComponent(location.href.slice(0, 480));
            frame.title = 'Live chat';
            layout();
            document.body.appendChild(frame);
            setOpen(true);
        } else {
            setOpen(frame.style.display === 'none');
        }
    }

    window.addEventListener('resize', function () {
        if (frame) {
            var isOpen = frame.style.display !== 'none';
            layout();
            setOpen(isOpen);
        }
    });

    button.addEventListener('click', open);

    window.addEventListener('message', function (event) {
        if (frame && event.source === frame.contentWindow && event.data && event.data.cw === 'close') {
            setOpen(false);
        }
    });

    (document.body ? Promise.resolve() : new Promise(function (r) { document.addEventListener('DOMContentLoaded', r); }))
        .then(function () { document.body.appendChild(button); });
})();
