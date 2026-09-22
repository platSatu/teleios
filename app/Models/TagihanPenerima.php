<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satu App\Models\TagihanPelanggan yang kena satu App\Models\Tagihan --
 * ini "invoice" yang sebenarnya orang bayar lewat halaman publik. Lihat
 * migration create_tagihan_penerima_table.php's docblock untuk
 * penjelasan lengkap tiap kolom (amount terkunci, denda_amount
 * tersimpan bukan computed-only, public_token terpisah dari id, dst).
 */
class TagihanPenerima extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_penerima';

    public const STATUS_BELUM_BAYAR = 'belum_bayar';

    public const STATUS_LUNAS = 'lunas';

    public const STATUS_KADALUARSA = 'kadaluarsa';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'tagihan_id',
        'tagihan_pelanggan_id',
        'company_id',
        'branch_office_id',
        'amount',
        'denda_amount',
        'status',
        'public_token',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'denda_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // public_token DIPISAH dari id (lihat migration's docblock) --
        // digenerate di sini, bukan diandalkan diisi manual oleh
        // caller, supaya tidak ada jalur pembuatan baris yang lupa
        // mengisinya.
        static::creating(function (self $penerima) {
            if (empty($penerima->public_token)) {
                $penerima->public_token = (string) Str::uuid();
            }
        });
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(TagihanPelanggan::class, 'tagihan_pelanggan_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(TagihanReminderLog::class);
    }

    /**
     * Riwayat percobaan bayar Duitku -- REUSE App\Models\PaymentTransaction
     * yang sudah ada (polymorphic, immutable, tidak bisa dihapus),
     * bukan tabel baru. Lihat docblock class itu: "Bisa ke: Deposit,
     * Subscription, Purchase" -- TagihanPenerima jadi salah satu jenis
     * reference_type baru buat tabel yang sama.
     */
    public function paymentTransactions(): MorphMany
    {
        return $this->morphMany(PaymentTransaction::class, 'reference');
    }

    public function isLunas(): bool
    {
        return $this->status === self::STATUS_LUNAS;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Berapa hari sudah lewat due_date tagihan induknya, dihitung dari
     * $asOf (default: sekarang). Negatif berarti belum jatuh tempo --
     * dipakai hitungDenda() di bawah, dan boleh dipakai langsung kalau
     * cuma perlu tahu keterlambatan tanpa nominal denda-nya.
     */
    public function hariTelat(?Carbon $asOf = null): int
    {
        $dueDate = $this->tagihan?->due_date;

        if (! $dueDate) {
            return 0;
        }

        return $dueDate->startOfDay()->diffInDays(($asOf ?? now())->startOfDay(), false);
    }

    /**
     * Hitung nominal denda per tanggal $asOf berdasarkan tier-tier
     * App\Models\TagihanDendaTier milik category tagihan ini -- TIDAK
     * menulis ke `denda_amount` (caller yang memutuskan kapan
     * menyimpannya, misal saat invoice Duitku dibuat atau lewat job
     * terjadwal), supaya nilai yang sudah tersimpan tidak berubah
     * diam-diam tiap kali baris ini sekadar dibaca.
     *
     * Fail-safe ke 0 kalau tagihan.pakai_denda mati, category tidak
     * mengaktifkan denda, atau belum jatuh tempo sama sekali -- sama
     * prinsipnya dengan App\Services\PackageLimitService yang fail-open
     * saat aturan belum/tidak dikonfigurasi.
     */
    public function hitungDenda(?Carbon $asOf = null): string
    {
        if (! $this->tagihan?->pakai_denda) {
            return '0.00';
        }

        $hariTelat = $this->hariTelat($asOf);

        if ($hariTelat <= 0) {
            return '0.00';
        }

        $tier = $this->tagihan->category?->dendaTiers
            ->first(fn (TagihanDendaTier $t) => $t->berlakuUntuk($hariTelat));

        if (! $tier) {
            return '0.00';
        }

        if ($tier->isPersenPerHari()) {
            $denda = bcmul((string) $this->amount, bcdiv((string) $tier->nilai, '100', 6), 2);

            return bcmul($denda, (string) $hariTelat, 2);
        }

        // Flat -- 'sekali' berapa pun lama telatnya, atau 'per_hari'
        // dikalikan sejak tier ini mulai berlaku (bukan sejak due_date,
        // supaya tidak dobel hitung dengan tier sebelumnya).
        if ($tier->frekuensi_flat === TagihanDendaTier::FREKUENSI_SEKALI) {
            return number_format((float) $tier->nilai, 2, '.', '');
        }

        $hariDalamTier = $hariTelat - $tier->mulai_hari_ke + 1;

        return bcmul((string) $tier->nilai, (string) max(1, $hariDalamTier), 2);
    }
}
