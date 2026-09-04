<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris histori "jadwal sebelum diganti" / "jadwal sesudah
 * diganti" — ditulis App\Services\Jadwal\JadwalScheduleChangeNotifier.
 * Lihat migration create_jadwal_change_logs_table's docblock untuk
 * kenapa `before`/`after` JSON bebas-bentuk (bukan kolom per-field),
 * dan kenapa satu perubahan lewat form Student bisa jadi DUA baris
 * (before-only + after-only) sementara satu edit langsung Jadwal Kelas
 * cukup SATU baris (before+after sekaligus).
 *
 * Read-only dari sisi app — tidak ada UPDATE/DELETE terhadap baris ini
 * di mana pun, murni jejak historis (mirip semangat App\Models\AuditLog,
 * meski tidak sekeras itu — tidak ada guard yang menolak update/delete
 * di level model ini).
 */
class JadwalChangeLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_change_logs';

    public const SOURCE_STUDENT_FORM = 'student_form';

    public const SOURCE_JADWAL_KELAS_EDIT = 'jadwal_kelas_edit';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'student_id',
        'jadwal_kelas_id',
        'source',
        'before',
        'after',
        'changed_by',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(JadwalStudent::class, 'student_id');
    }

    public function jadwalKelas(): BelongsTo
    {
        return $this->belongsTo(JadwalKelas::class, 'jadwal_kelas_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
