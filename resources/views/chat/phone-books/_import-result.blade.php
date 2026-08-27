{{-- Reusable "one import's result" rendering — shared by:
       - index.blade.php's session('importResult') block (only ever a
         stale flash from before imports moved to a background job, see
         App\Jobs\ProcessPhoneBookImport; kept so an old flash still
         renders instead of silently vanishing)
       - import-history.blade.php, which feeds it straight from an
         App\Models\WaPhoneBookImport row instead of session data.

     Expects:
       $createdCount     int    — count of rows actually created
       $errors           array  — App\Imports\PhoneBookImport::$errors shape: [{row,name,messages[]}]
       $skippedSheets    array  — optional, App\Imports\PhoneBookImport::$skippedSheets shape: [{sheet,row_count}]
       $autoOpenErrors   bool   — optional (default true), whether the "Lihat baris yang gagal" <details>
                                  starts expanded — import-history.blade.php passes false for every row
                                  except the most recent one, so a long history page doesn't render with
                                  every row's error list already sprawled open.
--}}
@php
    $skippedSheets = $skippedSheets ?? [];
    $autoOpenErrors = $autoOpenErrors ?? true;
@endphp
<div class="alert {{ (empty($errors) && empty($skippedSheets)) ? 'alert-success' : 'alert-warning' }} mb-0">
    <div class="fw-semibold mb-1">
        {{ $createdCount }} kontak berhasil dibuat{{ empty($errors) ? '.' : ', ' . count($errors) . ' baris gagal.' }}
    </div>

    @if (!empty($skippedSheets))
        <ul class="small mb-2">
            @foreach ($skippedSheets as $sheet)
                <li>
                    Sheet ke-{{ $sheet['sheet'] }} dilewati karena lebih dari {{ \App\Imports\PhoneBookImport::MAX_ROWS }} baris
                    data ({{ $sheet['row_count'] }} baris data ditemukan). Pisahkan jadi beberapa file lalu import ulang bagian ini.
                </li>
            @endforeach
        </ul>
    @endif

    @if (!empty($errors))
        <details @if ($autoOpenErrors) open @endif>
            <summary class="small text-muted" style="cursor: pointer;">Lihat baris yang gagal</summary>
            <ul class="small mb-0 mt-2">
                @foreach ($errors as $err)
                    <li>Baris {{ $err['row'] }}{{ !empty($err['name']) ? ' (' . $err['name'] . ')' : '' }}: {{ implode(' ', $err['messages']) }}</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
