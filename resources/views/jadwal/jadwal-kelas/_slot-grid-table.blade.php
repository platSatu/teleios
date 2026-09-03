{{--
    Partial tabel grid slot 30 menit -- dipakai App\Http\Controllers\
    Jadwal\JadwalKelasController::index() untuk KEDUA mode
    pengelompokan ($groupBy, lihat class docblock update 7 September
    2026): 'ruangan' (kolom variabel = Pengajar) dan 'pengajar' (kolom
    variabel = Ruangan). Diekstrak dari index.blade.php supaya markup
    baris (dropdown kehadiran, badge sesi pengganti, tombol aksi) tidak
    dobel antara 2 mode.

    Variabel wajib dari pemanggil:
    - $slotRows        : hasil JadwalKelasController::buildSlotGrid()
    - $groupBy          : 'ruangan' | 'pengajar'
    - $dateStr           : tanggal grid ini ("Y-m-d")
    - $activeRuanganId  : diisi kalau $groupBy === 'ruangan'
    - $activePengajarId : diisi kalau $groupBy === 'pengajar'
--}}
@php
    $extraLabel = $groupBy === 'ruangan' ? 'Pengajar' : 'Ruangan';
    $createExtraParams = $groupBy === 'ruangan'
        ? ['ruangan_id' => $activeRuanganId]
        : ['pengajar_id' => $activePengajarId, 'group_by' => 'pengajar'];
@endphp
<div class="table-responsive">
    <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1100px;">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap" style="width: 110px;">Waktu</th>
                <th class="text-nowrap">{{ $extraLabel }}</th>
                <th class="text-nowrap">Bidang</th>
                <th class="text-nowrap">Kategori</th>
                <th class="text-nowrap">Murid</th>
                <th class="text-nowrap" style="min-width: 340px;">Kehadiran</th>
                <th class="text-nowrap">Status</th>
                <th class="text-end text-nowrap">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($slotRows as $row)
                @php $kelas = $row['kelas']; @endphp
                @if($row['isBreak'] && !$kelas)
                    <tr class="table-secondary">
                        <td class="text-nowrap fw-semibold">{{ $row['time'] }}</td>
                        <td colspan="7" class="text-muted text-center"><i class="ri-cup-line"></i> Jam Istirahat</td>
                    </tr>
                @elseif($kelas)
                    <tr>
                        <td class="text-nowrap fw-semibold">
                            {{ $row['time'] }}{{ $kelas->end_time ? ' – '.$kelas->end_time->format('H:i') : '' }}
                        </td>
                        <td class="text-nowrap">{{ $groupBy === 'ruangan' ? ($kelas->pengajar->name ?? '-') : ($kelas->ruangan->name ?? '-') }}</td>
                        <td class="text-nowrap">{{ $kelas->mataPelajaran->name ?? '-' }}</td>
                        <td class="text-nowrap">{{ $kelas->kategori->name ?? '-' }}</td>
                        <td class="text-nowrap">{{ $kelas->student->name ?? 'Slot Kosong' }}</td>
                        <td>
                            <form action="{{ route('jadwal.kelas.attendance.update', $kelas->id) }}" method="POST" class="d-flex flex-nowrap align-items-center gap-2 mb-1">
                                @csrf
                                @method('PATCH')
                                <select name="attendance_status" class="form-select form-select-sm" style="width: 150px; flex: 0 0 auto;" onchange="this.form.submit()">
                                    <option value="" @selected(!$kelas->attendance_status)>Belum Diabsen</option>
                                    <option value="hadir" @selected($kelas->attendance_status === 'hadir')>Hadir</option>
                                    <option value="tidak_hadir" @selected($kelas->attendance_status === 'tidak_hadir')>Tidak Hadir (hangus)</option>
                                    <option value="izin" @selected($kelas->attendance_status === 'izin')>Izin/Sakit (dapat pengganti)</option>
                                </select>
                                <div class="input-group input-group-sm" style="width: 160px; flex: 0 0 auto;">
                                    <input type="text" name="attendance_notes" value="{{ $kelas->attendance_notes }}" class="form-control form-control-sm" placeholder="Keterangan">
                                    <button type="submit" class="btn btn-outline-secondary" title="Simpan keterangan"><i class="ri-save-line"></i></button>
                                </div>
                            </form>
                            @if($kelas->attendance_status === 'izin')
                                @if($kelas->sesiPengganti)
                                    <span class="badge bg-info-subtle text-info">
                                        <i class="ri-repeat-line"></i> Pengganti: {{ $kelas->sesiPengganti->start_time?->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    <a href="{{ route('jadwal.kelas.create', ['pengganti_dari_sesi_id' => $kelas->id]) }}" class="btn btn-xs btn-outline-info" style="font-size: .75rem; padding: .1rem .4rem;">
                                        <i class="ri-add-line"></i> Buat Sesi Pengganti
                                    </a>
                                @endif
                            @endif
                            @if($kelas->penggantiDariSesi)
                                <span class="badge bg-secondary-subtle text-secondary d-block mt-1" style="width: fit-content;">
                                    Pengganti dari {{ $kelas->penggantiDariSesi->start_time?->format('d/m/Y H:i') }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $kelas->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $kelas->status }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            @if($kelas->start_time)
                                <form action="{{ route('jadwal.kelas.pengajar-reminder.send', $kelas->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Kirim rekap jadwal ke pengajar {{ $kelas->pengajar->name ?? '' }} untuk tanggal {{ $kelas->start_time->format('d/m/Y') }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light" title="Kirim reminder ke pengajar">
                                        <i class="ri-notification-3-line"></i>
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('jadwal.kelas.edit', ['id' => $kelas->id, 'group_by' => $groupBy]) }}" class="btn btn-sm btn-light">
                                <i class="ri-edit-line"></i>
                            </a>
                            <form action="{{ route('jadwal.kelas.destroy', ['id' => $kelas->id, 'group_by' => $groupBy]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus Jadwal Kelas ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                            </form>
                        </td>
                    </tr>
                @else
                    <tr class="text-muted">
                        <td class="text-nowrap fw-semibold">{{ $row['time'] }}</td>
                        <td colspan="7">
                            <a href="{{ route('jadwal.kelas.create', $createExtraParams + ['start_time' => $dateStr.'T'.$row['time']]) }}" class="btn btn-sm btn-light text-muted">
                                <i class="ri-add-line"></i> Tambah sesi jam {{ $row['time'] }}
                            </a>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Jam Operasional belum diatur untuk branch ini, grid slot tidak bisa ditampilkan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
