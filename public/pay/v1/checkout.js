/*!
 * teleios Payment Gateway popup -- TeleiosPay.open(token, { onSuccess, onClose })
 * Membuka halaman checkout teleios di iframe (overlay) di atas website Anda.
 * onSuccess hanya untuk tampilan; status lunas yang sah selalu dari webhook
 * / API di server Anda.
 */
(function (w, d) {
    if (w.TeleiosPay) return;

    var script = d.currentScript;
    var base = script ? new URL(script.src).origin : '';
    var overlay = null, opts = {};

    function close() {
        if (!overlay) return;
        overlay.remove();
        overlay = null;
        d.body.style.overflow = '';
        w.removeEventListener('message', onMessage);
        opts.onClose && opts.onClose();
    }

    function onMessage(e) {
        if (e.origin !== base || !e.data || e.data.source !== 'teleios-pay') return;
        if (e.data.event === 'success') opts.onSuccess && opts.onSuccess(e.data);
        if (e.data.event === 'close') close();
    }

    w.TeleiosPay = {
        open: function (token, options) {
            if (!/^[0-9a-f-]{36}$/.test(String(token))) throw new Error('TeleiosPay: token tidak valid');
            if (overlay) close();
            opts = options || {};

            overlay = d.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;background:rgba(15,20,35,.55);display:flex;align-items:center;justify-content:center;';

            var frame = d.createElement('iframe');
            frame.src = base + '/pay/' + token;
            frame.title = 'Pembayaran';
            frame.allow = 'clipboard-write';
            frame.style.cssText = 'width:100%;max-width:460px;height:100%;max-height:720px;border:0;background:transparent;';

            overlay.appendChild(frame);
            overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
            d.body.appendChild(overlay);
            d.body.style.overflow = 'hidden';
            w.addEventListener('message', onMessage);
        },
        close: close
    };
})(window, document);
