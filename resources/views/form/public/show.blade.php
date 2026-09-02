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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #673ab7;
            --brand-dark: #4d2c91;
            --brand-soft: #f3edfb;
            --bg: #f4f2fb;
            --border: #e6e1f5;
            --text: #202124;
            --muted: #5f6368;
            --danger: #d93025;
            --success: #188038;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
            line-height: 1.55;
            padding-bottom: 56px;
            -webkit-font-smoothing: antialiased;
        }
        .pf-topbar {
            height: 8px;
            background: linear-gradient(90deg, var(--brand), #8561c5);
        }
        .pf-wrap {
            max-width: 700px;
            margin: 0 auto;
            padding: 32px 16px;
        }
        @media (max-width: 600px) {
            .pf-wrap { padding: 16px 10px; }
        }

        .pf-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 1px 3px 1px rgba(60,64,67,.15);
            transition: box-shadow .15s ease;
        }
        .pf-banner {
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            display: block;
        }
        .pf-card-body { padding: 24px 24px; }
        @media (max-width: 600px) {
            .pf-card-body { padding: 18px 16px; }
        }

        .pf-header-card .pf-card-body { border-top: 10px solid var(--brand); }
        .pf-header-card.pf-has-banner .pf-card-body { border-top: none; }

        .pf-title { font-size: 28px; font-weight: 400; margin: 0 0 10px; color: var(--text); letter-spacing: -0.2px; }
        .pf-desc { color: var(--text); font-size: 14px; white-space: pre-line; margin: 0 0 14px; opacity: .8; }
        .pf-period { font-size: 12.5px; color: var(--muted); border-top: 1px solid var(--border); padding-top: 14px; margin-top: 4px; display: flex; align-items: center; gap: 6px; }

        .pf-alert { border-radius: 8px; padding: 14px 16px; font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .pf-alert-success { background: #e6f4ea; color: var(--success); border: 1px solid #b7e1c1; }
        .pf-alert-danger { background: #fce8e6; color: var(--danger); border: 1px solid #f6c6c1; }

        .pf-question-card { border-left: 3px solid transparent; }
        .pf-question-card:focus-within { border-left-color: var(--brand); }

        .pf-field-label { font-size: 15px; font-weight: 400; margin-bottom: 14px; display: block; color: var(--text); }
        .pf-required { color: var(--danger); margin-left: 3px; }

        .pf-input, .pf-textarea, .pf-file {
            width: 100%;
            border: none;
            border-bottom: 1px solid #dadce0;
            border-radius: 0;
            padding: 8px 2px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            background: transparent;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .pf-input:focus, .pf-textarea:focus {
            outline: none;
            border-bottom: 2px solid var(--brand);
            padding-bottom: 7px;
        }
        .pf-textarea { resize: vertical; min-height: 70px; border: 1px solid #dadce0; border-radius: 6px; padding: 10px 12px; }
        .pf-textarea:focus { border: 2px solid var(--brand); padding: 9px 11px; }

        .pf-file {
            border: 1px dashed #c7c2d6;
            border-radius: 6px;
            padding: 12px;
            background: #fbfaff;
        }

        .pf-option-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 4px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 6px;
        }
        .pf-option-row:hover { background: var(--brand-soft); }

        /* Radio & checkbox kustom -- meniru gaya Material/Google Forms
           (lingkaran/kotak dengan ring ungu saat dipilih), bukan style
           browser bawaan. */
        .pf-option-row input[type="radio"],
        .pf-option-row input[type="checkbox"] {
            -webkit-appearance: none;
            appearance: none;
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            border: 2px solid #5f6368;
            margin: 0;
            position: relative;
            cursor: pointer;
            transition: border-color .1s ease;
        }
        .pf-option-row input[type="radio"] { border-radius: 50%; }
        .pf-option-row input[type="checkbox"] { border-radius: 3px; }
        .pf-option-row input[type="radio"]:checked,
        .pf-option-row input[type="checkbox"]:checked {
            border-color: var(--brand);
        }
        .pf-option-row input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 3px; left: 3px;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--brand);
        }
        .pf-option-row input[type="checkbox"]:checked {
            background: var(--brand);
        }
        .pf-option-row input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 5px; top: 1px;
            width: 4px; height: 9px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .pf-file-hint { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .pf-field-error { color: var(--danger); font-size: 12px; margin-top: 8px; display: flex; align-items: center; gap: 4px; }

        .pf-submit-row { display: flex; align-items: center; justify-content: space-between; }
        .pf-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--brand);
            color: #fff;
            border: none;
            padding: 10px 28px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(60,64,67,.3);
            transition: background .15s ease, box-shadow .15s ease;
        }
        .pf-btn:hover { background: var(--brand-dark); box-shadow: 0 2px 6px rgba(103,58,183,.4); }

        .pf-closed { text-align: center; padding: 48px 20px; color: var(--muted); }
        .pf-closed i { font-size: 42px; display: block; margin-bottom: 14px; color: var(--brand); }
        .pf-closed-title { font-size: 16px; font-weight: 500; color: var(--text); margin-bottom: 4px; }

        .pf-footer-note { font-size: 13px; color: var(--muted); white-space: pre-line; margin: 0; }
        .pf-page-footer { text-align: center; color: var(--muted); font-size: 12px; margin-top: 28px; }
    </style>
</head>
<body>
    <div class="pf-topbar"></div>

    <div class="pf-wrap">

        @if (session('success'))
            <div class="pf-alert pf-alert-success"><i class="ri-checkbox-circle-line fs-16"></i> {{ session('success') }}</div>
        @endif

        <div class="pf-card pf-header-card {{ $header->background_url ? 'pf-has-banner' : '' }}">
            @if ($header->background_url)
                <img src="{{ $header->background_url }}" alt="" class="pf-banner">
            @endif
            <div class="pf-card-body">
                <h1 class="pf-title">{{ $header->name }}</h1>
                @if ($header->description)
                    <p class="pf-desc">{{ $header->description }}</p>
                @endif
                <div class="pf-period">
                    <i class="ri-time-line"></i>
                    Dibuka {{ $header->start_date?->translatedFormat('d M Y H:i') }}
                    &ndash; Ditutup {{ $header->end_date?->translatedFormat('d M Y H:i') }}
                </div>
            </div>
        </div>

        @if (! $canSubmit)
            <div class="pf-card">
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
                    <div class="pf-card pf-question-card">
                        <div class="pf-card-body">
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
                    </div>
                @endforeach

                <div class="pf-card">
                    <div class="pf-card-body pf-submit-row">
                        <button type="submit" class="pf-btn"><i class="ri-send-plane-2-line"></i> Kirim</button>
                        <span class="fs-12" style="color: var(--muted);"><span class="pf-required">*</span> Wajib diisi</span>
                    </div>
                </div>
            </form>
        @endif

        @if ($header->footers->isNotEmpty())
            <div class="pf-card">
                <div class="pf-card-body">
                    @foreach ($header->footers as $footer)
                        <p class="pf-footer-note">{{ $footer->name }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="pf-page-footer">&copy; {{ date('Y') }} {{ config('app.name', 'Konexa') }}</div>
    </div>
</body>
</html>
