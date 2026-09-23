<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis tagihan per cabang (contoh: "Uang Sekolah", "Uang Pangkal",
 * "Uang Seragam", "Uang Buku") -- lihat migration create_tagihan_category_table.php's
 * docblock untuk alasan desain lengkap (kenapa branch-scoped).
 *
 * 23 September 2026 redesign: `is_recurring`/`default_amount`/
 * `denda_enabled` dihapus (nominal selalu diisi manual per Tagihan,
 * "berulang" cuma label tanpa efek nyata). Denda/pengingat/nomor-invoice
 * sekarang dikonfigurasi lewat halaman "Setting Tagihan" (lihat
 * App\Http\Controllers\Tagihan\TagihanCategorySettingController) dan
 * disimpan langsung di kolom-kolom denda_* / invoice_prefix /
 * notifikasi_email_aktif di bawah, bukan lagi lewat tabel
 * tagihan_denda_tier yang bebas-bentuk (App\Models\TagihanDendaTier
 * masih ada di DB untuk kompatibilitas historis, tapi tidak dipakai
 * jalur baru ini) -- lihat App\Models\TagihanPenerima::hitungDenda().
 */
class TagihanCategory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_category';

    /** Satu nominal denda tetap, berapa pun lama telatnya. */
    public const DENDA_MODE_FLAT = 'flat';

    /** Persentase dari amount, dikalikan hari atau bulan telat. */
    public const DENDA_MODE_PERSENTASE = 'persentase';

    /**
     * Persentase per hari sampai `denda_persen_sampai_hari` hari
     * telat, lalu SETELAH itu ditambah flat per hari (akumulatif --
     * bukan ganti tier, tapi persentase yang sudah terkumpul + flat).
     */
    public const DENDA_MODE_PERSENTASE_FLAT = 'persentase_flat';

    public const DENDA_MODE_LIST = [self::DENDA_MODE_FLAT, self::DENDA_MODE_PERSENTASE, self::DENDA_MODE_PERSENTASE_FLAT];

    public const PERSEN_FREKUENSI_PER_HARI = 'per_hari';

    public const PERSEN_FREKUENSI_PER_BULAN = 'per_bulan';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'deskripsi',
        'denda_mode',
        'denda_flat_amount',
        'denda_persen',
        'denda_persen_frekuensi',
        'denda_persen_sampai_hari',
        'invoice_prefix',
        'notifikasi_email_aktif',
        'status',
    ];

    protected $casts = [
        'denda_flat_amount' => 'decimal:2',
        'denda_persen' => 'decimal:4',
        'denda_persen_sampai_hari' => 'integer',
        'notifikasi_email_aktif' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    /**
     * Peninggalan sistem tier bebas -- tidak dipakai jalur denda baru
     * (denda_mode di atas), disisakan cuma supaya data lama yang
     * mungkin masih ada di tabel tagihan_denda_tier tidak yatim piatu
     * relasinya kalau suatu saat perlu diaudit/dibersihkan manual.
     */
    public function dendaTiers(): HasMany
    {
        return $this->hasMany(TagihanDendaTier::class)->orderBy('urutan');
    }

    /**
     * Template aturan pengingat milik category ini -- disalin ke
     * App\Models\TagihanReminderRule (per-Tagihan) tiap kali admin
     * membuat Tagihan baru di bawah category ini (lihat
     * TagihanController::store()), supaya tiap Tagihan tetap boleh
     * override/hapus aturan sendiri tanpa mengubah template-nya.
     */
    public function reminderRuleTemplates(): HasMany
    {
        return $this->hasMany(TagihanCategoryReminderRule::class);
    }

    /** Semua Tagihan (periode/invoice) yang pernah dibuat di bawah category ini. */
    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    /**
     * Daftar "langganan" -- TagihanPelanggan mana saja yang tercentang
     * masuk category ini, dipakai buat mengisi TagihanPenerima otomatis
     * saat admin membuat Tagihan baru. Lihat migration
     * create_tagihan_category_pelanggan_table.php's docblock.
     */
    public function categoryPelanggan(): HasMany
    {
        return $this->hasMany(TagihanCategoryPelanggan::class);
    }
}
