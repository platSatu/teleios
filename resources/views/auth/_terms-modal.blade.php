{{-- Dipakai halaman login & register. --}}
    <!-- Syarat & Ketentuan popup — moved here, as a direct child of
         <body>, instead of living inside .main-wrapper (which has
         overflow-hidden + is inside a transformed/animated decoration
         wrapper on this page). Bootstrap's .modal/.modal-backdrop are
         position: fixed, which is normally relative to the viewport —
         but nested inside an ancestor with overflow-hidden (and,
         depending on the theme's CSS, a transform on top of that), fixed
         positioning becomes relative to THAT ancestor instead, which is
         exactly what clipped the popup and made the backdrop only cover
         part of the screen (and made outside-clicks land outside the
         real clickable area, so Tutup/X felt broken). Content is
         App\Models\WebTermCondition::current() — whichever row a
         superadmin has marked Active (see
         Superadmin\Web\TermConditionController). -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $currentTerms->name ?? 'Syarat dan Ketentuan' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($currentTerms)
                        {{-- Markdown -> HTML aman, lihat WebTermCondition::descriptionsHtml(). --}}
                        <style>
                            .terms-body h2 { font-size: 1rem; font-weight: 600; margin: 1.25rem 0 .5rem; }
                            .terms-body h3 { font-size: .9375rem; font-weight: 600; margin: 1rem 0 .5rem; }
                            .terms-body ol, .terms-body ul { padding-left: 1.25rem; }
                        </style>
                        <div class="terms-body">{!! $currentTerms->descriptionsHtml() !!}</div>
                    @else
                        <p class="text-muted mb-0">Syarat dan Ketentuan belum tersedia.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
