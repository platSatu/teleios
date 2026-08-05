@extends('layouts.dashboard')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">Kontak</h4>
                            <p class="text-muted mb-0">
                                Kontak dibuat otomatis begitu ada chat yang dibuka di Inbox. Atur nama, cabang, dan
                                siapa yang menangani dari sini.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <input type="text" id="wa-contact-search" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Cari nama/nomor...">

                        @if (! $lockedBranchId)
                            <select id="wa-contact-filter-branch" class="form-select form-select-sm" style="max-width: 200px;">
                                <option value="">Semua Cabang</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <select id="wa-contact-filter-assigned" class="form-select form-select-sm" style="max-width: 200px;">
                            <option value="">Semua Assignee</option>
                            @foreach ($teamMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Nomor</th>
                                    <th>Sumber</th>
                                    @if (! $lockedBranchId)
                                        <th>Cabang</th>
                                    @endif
                                    <th>Ditugaskan ke</th>
                                    <th>Terakhir Dihubungi</th>
                                </tr>
                            </thead>
                            <tbody id="wa-contact-table-body">
                                <tr id="wa-contact-table-empty">
                                    <td colspan="6" class="text-center text-muted py-4">Memuat kontak...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
        (function () {
            const listUrl = @json(route('chat.contacts.list'));
            const updateUrlTemplate = @json(route('chat.contacts.update', ['contact' => '__ID__']));
            const csrfToken = @json(csrf_token());
            const showBranchColumn = @json(! $lockedBranchId);
            const branches = @json($branches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name]));
            const teamMembers = @json($teamMembers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name]));

            const tableBody = document.getElementById('wa-contact-table-body');
            const searchInput = document.getElementById('wa-contact-search');
            const branchFilter = document.getElementById('wa-contact-filter-branch');
            const assignedFilter = document.getElementById('wa-contact-filter-assigned');

            let searchDebounce = null;

            function urlFor(template, id) {
                return template.replace('__ID__', id);
            }

            function fetchJson(url, options) {
                return fetch(url, Object.assign({ headers: { 'Accept': 'application/json' } }, options))
                    .then(function (res) { return res.json(); });
            }

            function timeLabel(iso) {
                if (!iso) return '-';
                const date = new Date(iso);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
            }

            function buildListUrl() {
                const params = new URLSearchParams();
                if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
                if (branchFilter && branchFilter.value) params.set('branch_office_id', branchFilter.value);
                if (assignedFilter.value) params.set('assigned_to', assignedFilter.value);
                const qs = params.toString();
                return listUrl + (qs ? ('?' + qs) : '');
            }

            function selectOptions(items, selectedId, emptyLabel) {
                let html = '<option value="">' + emptyLabel + '</option>';
                items.forEach(function (item) {
                    const selected = String(item.id) === String(selectedId) ? ' selected' : '';
                    html += '<option value="' + item.id + '"' + selected + '>' + item.name + '</option>';
                });
                return html;
            }

            function saveField(contactId, field, value, cell) {
                const payload = {};
                payload[field] = value === '' ? null : value;

                fetchJson(urlFor(updateUrlTemplate, contactId), {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                }).then(function (data) {
                    if (data.error) {
                        alert(data.error);
                    }
                });
            }

            function renderRow(contact) {
                const row = document.createElement('tr');

                const nameCell = document.createElement('td');
                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.className = 'form-control form-control-sm';
                nameInput.value = contact.name || '';
                nameInput.placeholder = '+' + contact.phone;
                nameInput.addEventListener('change', function () {
                    saveField(contact.id, 'name', nameInput.value.trim());
                });
                nameCell.appendChild(nameInput);
                row.appendChild(nameCell);

                const phoneCell = document.createElement('td');
                phoneCell.textContent = '+' + contact.phone;
                phoneCell.className = 'text-muted';
                row.appendChild(phoneCell);

                const sourceCell = document.createElement('td');
                const sourceBadge = document.createElement('span');
                sourceBadge.className = 'badge ' + (contact.source === 'manual' ? 'bg-secondary-subtle text-secondary' : 'bg-success-subtle text-success');
                sourceBadge.textContent = contact.source === 'manual' ? 'Manual' : 'WhatsApp';
                sourceCell.appendChild(sourceBadge);
                row.appendChild(sourceCell);

                if (showBranchColumn) {
                    const branchCell = document.createElement('td');
                    const branchSelect = document.createElement('select');
                    branchSelect.className = 'form-select form-select-sm';
                    branchSelect.innerHTML = selectOptions(branches, contact.branch_office_id, 'Belum ada cabang');
                    branchSelect.addEventListener('change', function () {
                        saveField(contact.id, 'branch_office_id', branchSelect.value);
                    });
                    branchCell.appendChild(branchSelect);
                    row.appendChild(branchCell);
                }

                const assignedCell = document.createElement('td');
                const assignedSelect = document.createElement('select');
                assignedSelect.className = 'form-select form-select-sm';
                assignedSelect.innerHTML = selectOptions(teamMembers, contact.assigned_to, 'Belum ditugaskan');
                assignedSelect.addEventListener('change', function () {
                    saveField(contact.id, 'assigned_to', assignedSelect.value);
                });
                assignedCell.appendChild(assignedSelect);
                row.appendChild(assignedCell);

                const lastContactedCell = document.createElement('td');
                lastContactedCell.textContent = timeLabel(contact.last_contacted_at);
                lastContactedCell.className = 'text-muted small';
                row.appendChild(lastContactedCell);

                return row;
            }

            function loadContacts() {
                fetchJson(buildListUrl()).then(function (data) {
                    const contacts = data.contacts || [];
                    tableBody.innerHTML = '';

                    if (contacts.length === 0) {
                        const row = document.createElement('tr');
                        const colspan = showBranchColumn ? 6 : 5;
                        row.innerHTML = '<td colspan="' + colspan + '" class="text-center text-muted py-4">Belum ada kontak. Kontak muncul otomatis begitu chat dibuka di Inbox.</td>';
                        tableBody.appendChild(row);
                        return;
                    }

                    contacts.forEach(function (contact) {
                        tableBody.appendChild(renderRow(contact));
                    });
                });
            }

            searchInput.addEventListener('input', function () {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(loadContacts, 350);
            });

            if (branchFilter) {
                branchFilter.addEventListener('change', loadContacts);
            }
            assignedFilter.addEventListener('change', loadContacts);

            loadContacts();
        })();
        });
    </script>
@endsection
