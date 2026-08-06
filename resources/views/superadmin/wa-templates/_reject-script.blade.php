{{--
    Shared by index/show/uncategorized.blade.php — SweetAlert2-backed
    confirm/reject dialogs for every "Setujui"/"Tolak" form on these 3
    review pages, replacing the old native confirm()/prompt() calls
    (blunt browser dialogs that don't match the rest of the admin
    theme's look). SweetAlert2 itself ships with this theme
    (public/be/assets/libs/sweetalert2) but isn't loaded by the shared
    dashboard layout, so it's pulled in here rather than globally —
    keeps the extra ~70KB off every other admin page that has no use
    for it.

    Usage on a form:
      <form class="js-approve-form" data-confirm-text="Setujui kategori ini?" ...>
      <form class="js-reject-form" data-reject-title="Tolak kategori ini?" ...>
          <input type="hidden" name="reason">
      </form>

    Both classes intercept the native submit, show a SweetAlert2 dialog,
    and only call form.submit() (which — unlike a synchronous return
    from a real 'submit' listener — does NOT re-fire this same
    listener, so there's no risk of an infinite confirm loop) once the
    user actually confirms.
--}}
<link rel="stylesheet" href="{{ asset('be') }}/assets/libs/sweetalert2/sweetalert2.min.css">
<script src="{{ asset('be') }}/assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script>
    (function () {
        var swalTheme = {
            customClass: {
                confirmButton: 'btn btn-primary me-2',
                cancelButton: 'btn btn-light',
            },
            buttonsStyling: false,
        };

        document.querySelectorAll('form.js-approve-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                Swal.fire(Object.assign({
                    icon: 'question',
                    title: form.dataset.confirmText || 'Setujui item ini?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Setujui',
                    cancelButtonText: 'Batal',
                }, swalTheme)).then(function (result) {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

        document.querySelectorAll('form.js-reject-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                Swal.fire(Object.assign({
                    icon: 'warning',
                    title: form.dataset.rejectTitle || 'Tolak item ini?',
                    input: 'textarea',
                    inputPlaceholder: 'Alasan penolakan (akan terlihat oleh perusahaan terkait)...',
                    inputValidator: function (value) {
                        if (!value || !value.trim()) {
                            return 'Alasan penolakan wajib diisi.';
                        }
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Tolak',
                    cancelButtonText: 'Batal',
                }, swalTheme)).then(function (result) {
                    if (result.isConfirmed) {
                        form.querySelector('input[name="reason"]').value = result.value.trim();
                        form.submit();
                    }
                });
            });
        });
    })();
</script>
