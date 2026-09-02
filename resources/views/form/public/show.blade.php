<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $header->name }} | {{ config('app.name', 'Konexa') }}</title>
    <meta name="robots" content="noindex, nofollow">
    @if ($header->description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($header->description), 160) }}">
    @endif
    <link href="{{ asset('be') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #673ab7;
            --brand-dark: #4d2c91;
            --brand-soft: #f3edfb;
            --bg: #eef0f7;
            --border: #e6e1f5;
            --text: #1b1b23;
            --muted: #6b6f80;
            --danger: #d93025;
            --success: #188038;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        /* ===== Page shell — kartu besar 2 kolom (panel visual + form),
                 meniru layout referensi "Sign Up" modern/minimalis yang
                 diminta: panel kiri jadi tempat identitas form (nama,
                 periode, deskripsi) dengan warna brand polos sebagai
                 pengganti ilustrasi (tidak ada aset ilustrasi di app
                 ini), atau foto banner form kalau admin sudah upload
                 satu lewat pengaturan Form Header. ===== */
        .pf-page { min-height: 100vh; padding: 40px 20px; }
        .pf-shell {
            max-width: 1120px;
            margin: 0 auto;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 30px 60px -20px rgba(76, 44, 145, .25), 0 4px 12px rgba(76, 44, 145, .08);
            overflow: hidden;
            display: grid;
            grid-template-columns: minmax(280px, 400px) 1fr;
            align-items: stretch;
        }
        @media (max-width: 860px) {
            .pf-shell { grid-template-columns: 1fr; border-radius: 20px; }
        }

        .pf-visual {
            position: sticky;
            top: 0;
            align-self: start;
            min-height: 100%;
            background: linear-gradient(155deg, var(--brand) 0%, var(--brand-dark) 100%);
            background-size: cover;
            background-position: center;
            color: #fff;
            padding: 44px 36px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            overflow: hidden;
        }
        @media (max-width: 860px) {
            .pf-visual { position: static; min-height: 0; padding: 32px 24px; }
        }
        .pf-visual.pf-has-photo::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(30, 15, 60, .25) 0%, rgba(20, 10, 45, .85) 100%);
        }
        .pf-visual-decor {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }
        .pf-visual-decor.d1 { width: 180px; height: 180px; top: -60px; right: -60px; }
        .pf-visual-decor.d2 { width: 110px; height: 110px; bottom: 30%; left: -40px; }
        .pf-visual-inner { position: relative; z-index: 1; }
        .pf-visual-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: .3px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, .16);
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 18px;
        }
        .pf-visual-title { font-size: 30px; font-weight: 800; margin: 0 0 12px; line-height: 1.2; }
        .pf-visual-desc { font-size: 14.5px; opacity: .88; margin: 0 0 22px; white-space: pre-line; }
        .pf-visual-period {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, .22);
            opacity: .92;
        }

        .pf-form-col { padding: 44px 40px; }
        @media (max-width: 860px) { .pf-form-col { padding: 28px 22px; } }
        @media (max-width: 460px) { .pf-form-col { padding: 24px 16px; } }
        .pf-form-inner { max-width: 480px; margin: 0 auto; }

        .pf-alert {
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pf-alert-success { background: #e6f4ea; color: var(--success); border: 1px solid #b7e1c1; }
        .pf-alert-danger { background: #fce8e6; color: var(--danger); border: 1px solid #f6c6c1; }

        .pf-field { margin-bottom: 20px; }
        .pf-field-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            display: block;
        }
        .pf-required { color: var(--danger); margin-left: 3px; }

        .pf-input, .pf-textarea {
            width: 100%;
            border: 1.5px solid transparent;
            border-radius: 14px;
            padding: 13px 16px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--text);
            background: #f2f1f8;
            transition: border-color .15s ease, background .15s ease;
        }
        .pf-input:focus, .pf-textarea:focus {
            outline: none;
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 4px var(--brand-soft);
        }
        .pf-textarea { resize: vertical; min-height: 88px; }

        .pf-file {
            width: 100%;
            border: 1.5px dashed #c7c2d6;
            border-radius: 14px;
            padding: 16px;
            background: #faf9fd;
            font-size: 13.5px;
            font-family: inherit;
        }
        .pf-file-hint { font-size: 12px; color: var(--muted); margin-top: 6px; }

        .pf-option-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 12px;
            background: #f2f1f8;
            margin-bottom: 8px;
            transition: background .15s ease;
        }
        .pf-option-row:hover { background: var(--brand-soft); }
        .pf-option-row input[type="radio"],
        .pf-option-row input[type="checkbox"] {
            -webkit-appearance: none;
            appearance: none;
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            border: 2px solid #9a95ad;
            margin: 0;
            position: relative;
            cursor: pointer;
            background: #fff;
            transition: border-color .1s ease;
        }
        .pf-option-row input[type="radio"] { border-radius: 50%; }
        .pf-option-row input[type="checkbox"] { border-radius: 5px; }
        .pf-option-row input[type="radio"]:checked,
        .pf-option-row input[type="checkbox"]:checked { border-color: var(--brand); }
        .pf-option-row input[type="radio"]:checked::after {
            content: ''; position: absolute; top: 3px; left: 3px;
            width: 8px; height: 8px; border-radius: 50%; background: var(--brand);
        }
        .pf-option-row input[type="checkbox"]:checked { background: var(--brand); }
        .pf-option-row input[type="checkbox"]:checked::after {
            content: ''; position: absolute; left: 5px; top: 1px;
            width: 4px; height: 9px; border: solid #fff;
            border-width: 0 2px 2px 0; transform: rotate(45deg);
        }

        .pf-field-error { color: var(--danger); font-size: 12px; margin-top: 8px; display: flex; align-items: center; gap: 4px; }

        .pf-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--brand-dark);
            color: #fff;
            border: none;
            padding: 15px 28px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .2px;
            cursor: pointer;
            box-shadow: 0 8px 20px -6px rgba(77, 44, 145, .55);
            transition: background .15s ease, transform .1s ease;
        }
        .pf-btn:hover { background: var(--brand); }
        .pf-btn:active { transform: translateY(1px); }
        .pf-submit-note { text-align: center; font-size: 12px; color: var(--muted); margin-top: 12px; }

        .pf-closed { text-align: center; padding: 40px 10px; color: var(--muted); }
        .pf-closed i { font-size: 40px; display: block; margin-bottom: 14px; color: var(--brand); }
        .pf-closed-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; }

        .pf-footer-note { font-size: 12.5px; color: var(--muted); white-space: pre-line; margin: 0 0 8px; }
        .pf-footer-notes { margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border); }
        .pf-page-footer { text-align: center; color: var(--muted); font-size: 12px; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="pf-page">
        <div class="pf-shell">

            <div class="pf-visual {{ $header->background_url ? 'pf-has-photo' : '' }}"
                @if ($header->background_url) style="background-image: url('{{ $header->background_url }}');" @endif>
                <div class="pf-visual-decor d1"></div>
                <div class="pf-visual-decor d2"></div>
                <div class="pf-visual-inner">
                    <span class="pf-visual-eyebrow"><i class="ri-quill-pen-line"></i> Form Pendaftaran</span>
                    <h1 class="pf-visual-title">{{ $header->name }}</h1>
                    @if ($header->description)
                        <p class="pf-visual-desc">{{ $header->description }}</p>
                    @endif
                    <div class="pf-visual-period">
                        <i class="ri-time-line"></i>
                        Dibuka {{ $header->start_date?->translatedFormat('d M Y H:i') }}
                        &ndash; Ditutup {{ $header->end_date?->translatedFormat('d M Y H:i') }}
                    </div>
                </div>
            </div>

            <div class="pf-form-col">
                <div class="pf-form-inner">

                    @if (session('success'))
                        <div class="pf-alert pf-alert-success"><i class="ri-checkbox-circle-line fs-16"></i> {{ session('success') }}</div>
                    @endif

                    @if (! $canSubmit)
                        <div class="pf-closed">
                            <i class="ri-lock-2-line"></i>
                            <div class="pf-closed-title">
                                @if ($header->status !== 'active')
                                    Form ini sedang tidak aktif
                                @elseif (now()->lessThan($header->start_date))
                                    Form ini belum dibuka
                                @else
                                    Form ini sudah ditutup
                                @endif
                            </div>
                            <div>
                                @if ($header->status !== 'active')
                                    Silakan hubungi penyelenggara untuk informasi lebih lanjut.
                                @elseif (now()->lessThan($header->start_date))
                                    Silakan kembali lagi mulai {{ $header->start_date->translatedFormat('d M Y H:i') }}.
                                @else
                                    Form ini sudah ditutup sejak {{ $header->end_date->translatedFormat('d M Y H:i') }}.
                                @endif
                            </div>
                        </div>
                    @else
                        <form action="{{ route('form.public.store', $header->slug) }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            @if ($errors->any())
                                <div class="pf-alert pf-alert-danger"><i class="ri-error-warning-line fs-16"></i> Ada isian yang belum sesuai. Mohon periksa kembali form di bawah.</div>
                            @endif

                            @foreach ($header->contents as $content)
                                @php
                                    $fieldKey = "answers.{$content->id}";
                                    $oldValue = old("answers.{$content->id}");
                                @endphp
                                <div class="pf-field">
                                    <label class="pf-field-label">
                                        {{ $content->name }}
                                        @if ($content->is_required)<span class="pf-required">*</span>@endif
                                    </label>

                                    @if ($content->type === 'single_line')
                                        <input type="text" name="answers[{{ $content->id }}]" class="pf-input"
                                            value="{{ $oldValue }}" {{ $content->is_required ? 'required' : '' }}>
                                    @elseif ($content->type === 'textarea')
                                        <textarea name="answers[{{ $content->id }}]" class="pf-textarea"
                                            {{ $content->is_required ? 'required' : '' }}>{{ $oldValue }}</textarea>
                                    @elseif ($content->type === 'single_choice')
                                        @foreach ($content->options ?? [] as $option)
                                            <label class="pf-option-row">
                                                <input type="radio" name="answers[{{ $content->id }}]" value="{{ $option }}"
                                                    @checked($oldValue === $option) {{ $content->is_required ? 'required' : '' }}>
                                                {{ $option }}
                                            </label>
                                        @endforeach
                                    @elseif ($content->type === 'multiple_choice')
                                        @foreach ($content->options ?? [] as $option)
                                            <label class="pf-option-row">
                                                <input type="checkbox" name="answers[{{ $content->id }}][]" value="{{ $option }}"
                                                    @checked(is_array($oldValue) && in_array($option, $oldValue))>
                                                {{ $option }}
                                            </label>
                                        @endforeach
                                    @elseif ($content->type === 'file_upload')
                                        @php
                                            $allowed = $content->allowed_file_types ?: 'pdf,jpg,jpeg,png';
                                            $accept = collect(explode(',', $allowed))->map(fn ($ext) => '.'.trim($ext))->implode(',');
                                        @endphp
                                        <input type="file" name="answers[{{ $content->id }}]" class="pf-file"
                                            accept="{{ $accept }}" {{ $content->is_required ? 'required' : '' }}>
                                        <div class="pf-file-hint">Format: {{ str_replace(',', ', ', $allowed) }} — maksimal 5MB.</div>
                                    @endif

                                    @error($fieldKey)
                                        <div class="pf-field-error"><i class="ri-error-warning-line"></i> {{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach

                            <button type="submit" class="pf-btn"><i class="ri-send-plane-2-line"></i> Kirim</button>
                            <div class="pf-submit-note"><span class="pf-required">*</span> Wajib diisi</div>
                        </form>
                    @endif

                    @if ($header->footers->isNotEmpty())
                        <div class="pf-footer-notes">
                            @foreach ($header->footers as $footer)
                                <p class="pf-footer-note">{{ $footer->name }}</p>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>

        </div>
        <div class="pf-page-footer">&copy; {{ date('Y') }} {{ config('app.name', 'Konexa') }}</div>
    </div>
</body>
</html>
