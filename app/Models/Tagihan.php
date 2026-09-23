<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu periode/invoice di bawah App\Models\TagihanCategory (contoh:
 * "Uang Sekolah Januari 2026"). Dibuat MANUAL oleh admin, bukan
 * digenerate otomatis -- lihat migration create_tagihan_table.php's
 * docblock untuk penjelasan lengkap (termasuk kenapa `amount`/
 * `pakai_denda` adalah salinan, bukan dibaca ulang dari category).
 */
class Tagihan extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'tagihan_category_id',
        'name',
        'amount',
        'due_date',
        'pakai_denda',
        'wa_template_message',
        'status',
    ];

    /** Dipakai App\Models\Tagihan::renderWaTemplate() kalau wa_template_message kosong. */
    public const DEFAULT_WA_TEMPLATE = "Halo {nama}, tagihan *{nama_tagihan}* sebesar Rp{nominal} jatuh tempo {jatuh_tempo}. Silakan bayar lewat link berikut:\n{link}";

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'pakai_denda' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagihanCategory::class, 'tagihan_category_id');
    }

    /** Orang-orang yang ditagih untuk periode ini. */
    public function penerima(): HasMany
    {
        return $this->hasMany(TagihanPenerima::class);
    }

    /** Jadwal pengingat (H-7/H-3/dst) khusus tagihan ini. */
    public function reminderRules(): HasMany
    {
        return $this->hasMany(TagihanReminderRule::class);
    }

    /**
     * Isi placeholder {nama}/{nama_tagihan}/{nominal}/{jatuh_tempo}/{link}
     * di wa_template_message (atau DEFAULT_WA_TEMPLATE kalau admin tidak
     * mengisi template sendiri di form "Buat Tagihan") -- dipakai
     * App\Jobs\SendTagihanLinkWaMessage saat mengirim link bayar otomatis
     * ke satu App\Models\TagihanPenerima.
     */
    public function renderWaTemplate(string $namaPelanggan, string $link): string
    {
        $template = $this->wa_template_message ?: self::DEFAULT_WA_TEMPLATE;

        return strtr($template, [
            '{nama}' => $namaPelanggan,
            '{nama_tagihan}' => $this->name,
            '{nominal}' => number_format((float) $this->amount, 0, ',', '.'),
            '{jatuh_tempo}' => $this->due_date->format('d M Y'),
            '{link}' => $link,
        ]);
    }
}
