{{--
    Shared create/edit form for App\Models\WaCustomerAutomationRule,
    included from both index.blade.php (inline create) and
    edit.blade.php (dedicated page) — same convention
    resources/views/chat/phone-books/_form.blade.php already uses.

    Expected variables: $formAction, $method ('POST'|'PUT'), $rule
    (nullable, for edit prefill), $tags, $dealStages, $teamMembers,
    $formIdSuffix (unique string so this page can safely include the
    form more than once without id collisions — not currently needed
    twice on one page, but cheap insurance).
--}}
@php
    $triggerConfig = $rule->trigger_config ?? [];
    $actionConfig = $rule->action_config ?? [];
    $selectedTrigger = old('trigger_type', $rule->trigger_type ?? '');
@endphp

<form action="{{ $formAction }}" method="POST" class="row g-3">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="col-md-6">
        <label class="form-label">Nama Aturan</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $rule->name ?? '') }}" required maxlength="150">
    </div>

    <div class="col-md-6">
        <label class="form-label">Trigger (Pemicu)</label>
        <select name="trigger_type" class="form-select wa-automation-trigger-select" data-suffix="{{ $formIdSuffix }}">
            <option value="">- Pilih trigger -</option>
            @foreach (\App\Models\WaCustomerAutomationRule::TRIGGER_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($selectedTrigger === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12 wa-automation-trigger-group" data-trigger-group="deal_stage_changed" data-suffix="{{ $formIdSuffix }}">
        <label class="form-label">Tahap Deal</label>
        <select name="trigger_stage" class="form-select">
            <option value="">- Pilih tahap -</option>
            @foreach ($dealStages as $stageValue => $stageLabel)
                <option value="{{ $stageValue }}" @selected(old('trigger_stage', $triggerConfig['stage'] ?? null) === $stageValue)>{{ $stageLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12 wa-automation-trigger-group" data-trigger-group="tag_added" data-suffix="{{ $formIdSuffix }}">
        <label class="form-label">Tag</label>
        <select name="trigger_tag_id" class="form-select">
            <option value="">- Pilih tag -</option>
            @foreach ($tags as $tag)
                <option value="{{ $tag->id }}" @selected(old('trigger_tag_id', $triggerConfig['tag_id'] ?? null) === $tag->id)>{{ $tag->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12 wa-automation-trigger-group" data-trigger-group="no_contact_days" data-suffix="{{ $formIdSuffix }}">
        <label class="form-label">Jumlah Hari Tanpa Kontak</label>
        <input type="number" name="trigger_days" class="form-control" min="1" value="{{ old('trigger_days', $triggerConfig['days'] ?? '') }}">
    </div>

    <div class="col-12">
        <hr>
        <p class="text-muted small mb-2">Aksi: buat tugas follow-up otomatis (App\Models\WaCustomerTask) saat trigger di atas terpenuhi.</p>
    </div>

    <div class="col-md-6">
        <label class="form-label">Judul Tugas</label>
        <input type="text" name="action_title" class="form-control" value="{{ old('action_title', $actionConfig['title'] ?? '') }}" required maxlength="200">
    </div>

    <div class="col-md-3">
        <label class="form-label">Jatuh Tempo (hari sejak dibuat)</label>
        <input type="number" name="action_due_in_days" class="form-control" min="0" value="{{ old('action_due_in_days', $actionConfig['due_in_days'] ?? 1) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Ditugaskan ke</label>
        <select name="action_assigned_to" class="form-select">
            <option value="">Belum ditugaskan</option>
            @foreach ($teamMembers as $member)
                <option value="{{ $member->id }}" @selected(old('action_assigned_to', $actionConfig['assigned_to'] ?? null) === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $rule ? 'Simpan' : 'Buat Aturan' }}</button>
        @if ($rule)
            <a href="{{ route('chat.automation-rules.index') }}" class="btn btn-light">Batal</a>
        @endif
    </div>
</form>

<script>
    (function () {
        function toggleTriggerGroups(select) {
            var suffix = select.getAttribute('data-suffix');
            var selected = select.value;
            document.querySelectorAll('.wa-automation-trigger-group[data-suffix="' + suffix + '"]').forEach(function (group) {
                group.style.display = (group.getAttribute('data-trigger-group') === selected) ? '' : 'none';
            });
        }

        document.querySelectorAll('.wa-automation-trigger-select').forEach(function (select) {
            toggleTriggerGroups(select);
            select.addEventListener('change', function () { toggleTriggerGroups(select); });
        });
    })();
</script>
