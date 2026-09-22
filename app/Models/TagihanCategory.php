<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis tagihan per cabang (contoh: "Uang Sekolah", "Uang Pangkal",
 * "Uang Seragam", "Uang Buku") -- lihat migration create_tagihan_category_table.php's
 * docblock untuk alasan desain lengkap (kenapa branch-scoped, kenapa
 * `is_recurring` tidak memicu job otomatis, dst).
 */
class TagihanCategory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_category';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'is_recurring',
        'default_amount',
        'denda_enabled',
        'status',
    ];

    protected $casts = [
        'is_recurring' => 'boolean',
        'default_amount' => 'decimal:2',
        'denda_enabled' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    /** Aturan denda bertingkat, diurutkan dari yang paling awal berlaku. */
    public function dendaTiers(): HasMany
    {
        return $this->hasMany(TagihanDendaTier::class)->orderBy('urutan');
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
