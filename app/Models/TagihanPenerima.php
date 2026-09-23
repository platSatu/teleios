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
        'order_number',
        'invoice_number',
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

            // merchantOrderId yang dikirim ke Duitku (lihat App\Http\
            // Controllers\Tagihan\Public\TagihanPublicController &
            // App\Http\Controllers\Tagihan\TagihanDuitkuCallbackController)
            // -- SENGAJA bukan public_token (itu kredensial akses
            // halaman publik, tidak boleh nongol di dashboard Duitku
            // atau di query string manapun) dan bukan juga `id` murni
            // (prefix "TGH-" dipakai TagihanDuitkuCallbackController
            // buat memastikan callback ini benar milik Tagihan, bukan
            // nyasar dari App\Models\Deposit yang pakai prefix "DEP-").
            if (empty($penerima->order_number)) {
                $penerima->order_number = 'TGH-'.now()->format('YmdHis').strtoupper(Str::random(6));
            }

            // Nomor invoice yang DITAMPILKAN ke pelanggan (halaman publik
            // & halaman Laporan admin) -- beda dari order_number di atas
            // (itu murni ID teknis buat Duitku, tidak pernah ditampilkan).
            // Prefix diambil dari App\Models\TagihanCategory::invoice_prefix
            // (diatur admin lewat Setting Tagihan tab "Invoice &
            // Notifikasi"), fallback "INV" kalau kategori belum mengisinya.
            // 23 September 2026 (permintaan user poin 3.6).
            if (empty($penerima->invoice_number)) {
                $prefix = $penerima->tagihan?->category?->invoice_prefix ?: 'INV';
                $penerima->invoice_number = strtoupper($prefix).'-'.strtoupper(Str::random(6));
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
     * Hitung nominal denda per tanggal $asOf berdasarkan `denda_mode`
     * category tagihan ini -- TIDAK menulis ke `denda_amount` (caller
     * yang memutuskan kapan menyimpannya, misal saat invoice Duitku
     * dibuat atau lewat job terjadwal), supaya nilai yang sudah
     * tersimpan tidak berubah diam-diam tiap kali baris ini sekadar
     * dibaca.
     *
     * Fail-safe ke 0 kalau tagihan.pakai_denda mati, category tidak
     * punya denda_mode, atau belum jatuh tempo sama sekali -- sama
     * prinsipnya dengan App\Services\PackageLimitService yang fail-open
     * saat aturan belum/tidak dikonfigurasi.
     *
     * 23 September 2026: diganti dari sistem tier bebas
     * (App\Models\TagihanDendaTier) ke 3 mode tetap milik category
     * (App\Models\TagihanCategory::DENDA_MODE_*), lebih gampang
     * dimengerti admin daripada bikin tier sendiri.
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

        $category = $this->tagihan->category;

        return match ($category?->denda_mode) {
            \App\Models\TagihanCategory::DENDA_MODE_FLAT => $this->hitungDendaFlat($category),
            \App\Models\TagihanCategory::DENDA_MODE_PERSENTASE => $this->hitungDendaPersentase($category, $hariTelat),
            \App\Models\TagihanCategory::DENDA_MODE_PERSENTASE_FLAT => $this->hitungDendaPersentaseFlat($category, $hariTelat),
            default => '0.00',
        };
    }

    /** Nominal tetap, berapa pun lama telatnya. */
    private function hitungDendaFlat(TagihanCategory $category): string
    {
        return number_format((float) $category->denda_flat_amount, 2, '.', '');
    }

    /**
     * Persentase dari amount, dikalikan jumlah hari (denda_persen_
     * frekuensi = per_hari) atau bulan telat (per_bulan -- dibulatkan
     * ke atas, jadi 1 hari telat sudah kena 1 bulan penuh, sama
     * seperti cara denda bulanan pada umumnya dihitung).
     */
    private function hitungDendaPersentase(TagihanCategory $category, int $hariTelat): string
    {
        $satuanTelat = $category->denda_persen_frekuensi === TagihanCategory::PERSEN_FREKUENSI_PER_BULAN
            ? (int) ceil($hariTelat / 30)
            : $hariTelat;

        $dendaPerSatuan = bcmul((string) $this->amount, bcdiv((string) $category->denda_persen, '100', 6), 2);

        return bcmul($dendaPerSatuan, (string) $satuanTelat, 2);
    }

    /**
     * Persentase per hari untuk `denda_persen_sampai_hari` hari
     * pertama keterlambatan, lalu SETELAH itu ditambah flat per hari
     * di atas persentase yang sudah terkumpul (akumulatif, bukan
     * ganti mode) -- contoh dari user: jatuh tempo tanggal 10, mulai
     * tanggal 11 kena persentase per hari, begitu lewat batas hari
     * yang diatur (mis. tanggal 25) kena persentase (dihitung sampai
     * batas itu saja) + flat per hari sejak batas itu, terus sampai
     * pelanggan bayar.
     */
    private function hitungDendaPersentaseFlat(TagihanCategory $category, int $hariTelat): string
    {
        $batasHari = (int) $category->denda_persen_sampai_hari;
        $hariPersen = min($hariTelat, $batasHari);

        $dendaPerHariPersen = bcmul((string) $this->amount, bcdiv((string) $category->denda_persen, '100', 6), 2);
        $totalPersen = bcmul($dendaPerHariPersen, (string) $hariPersen, 2);

        if ($hariTelat <= $batasHari) {
            return $totalPersen;
        }

        $hariFlat = $hariTelat - $batasHari;
        $totalFlat = bcmul((string) $category->denda_flat_amount, (string) $hariFlat, 2);

        return bcadd($totalPersen, $totalFlat, 2);
    }
}
