<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'packages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'category_application_id',
        'name',
        'description',
        'duration',
        'price',
        'status',
        'is_featured',
        'is_trial',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_trial' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryApplication(): BelongsTo
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    /**
     * Semua category (layanan) yang dicakup paket ini -- satu paket bisa
     * mencakup beberapa layanan sekaligus (mis. "Lengkap" = Chat + Form +
     * Jadwal + Tagihan). category_application_id di atas tetap diisi
     * (category utama, kolomnya NOT NULL) supaya kode lama yang masih
     * membacanya tidak rusak; sumber kebenaran cakupan layanan adalah
     * pivot ini. Lihat migration create_package_category_applications_table.
     */
    public function categoryApplications(): BelongsToMany
    {
        return $this->belongsToMany(CategoryApplication::class, 'package_category_applications')
            ->withTimestamps();
    }

    /**
     * Id semua category yang dicakup (pivot + category utama), unik.
     *
     * @return array<int, string>
     */
    public function categoryIds(): array
    {
        return $this->categoryApplications->pluck('id')
            ->push($this->category_application_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Nama layanan yang dicakup, untuk ditampilkan (mis. "Chat, Tagihan").
     */
    public function categoryNames(): string
    {
        return $this->categoryApplications->pluck('name')
            ->whenEmpty(fn ($names) => $names->push($this->categoryApplication?->name))
            ->filter()
            ->implode(', ');
    }

    /**
     * Paket yang mencakup salah satu category bernama $names -- lewat
     * pivot ATAU category utama (paket lama yang belum punya baris pivot
     * tetap terbaca).
     *
     * @param  array<int, string>  $names
     */
    public function scopeCoveringCategoryNames(Builder $query, array $names): Builder
    {
        return $query->where(function (Builder $q) use ($names) {
            $q->whereHas('categoryApplications', fn (Builder $c) => $c->whereIn('name', $names))
                ->orWhereHas('categoryApplication', fn (Builder $c) => $c->whereIn('name', $names));
        });
    }

    /**
     * Perkiraan jumlah bulan dari durasi (30 -> 1, 180 -> 6, 365 -> 12).
     */
    public function months(): int
    {
        return max(1, (int) round($this->duration / 30.4));
    }

    /**
     * Label durasi untuk tampilan: "Trial 3 Hari", "6 Bulan", "12 Bulan",
     * atau "N Hari" untuk durasi di bawah 28 hari.
     */
    public function durationLabel(): string
    {
        if ($this->is_trial) {
            return "Trial {$this->duration} Hari";
        }

        return $this->duration < 28 ? "{$this->duration} Hari" : $this->months().' Bulan';
    }

    /**
     * Harga setara per bulan (0 untuk paket gratis).
     */
    public function monthlyPrice(): float
    {
        return (float) $this->price / $this->months();
    }

    /**
     * Deskripsi sebagai daftar fitur: satu baris = satu fitur, urutan
     * sesuai ketikan superadmin. Tanda "-", "*" atau "•" di awal baris
     * diabaikan, baris kosong dilewati.
     *
     * @return array<int, string>
     */
    public function featureLines(): array
    {
        return collect(preg_split('/\R/', (string) $this->description))
            ->map(fn (string $line) => trim((string) preg_replace('/^[\s\-*•]+/u', '', $line)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Numeric usage ceilings this package sets (see App\Models\
     * PackageLimit / App\Services\PackageLimitService) — a package with
     * no rows here is unlimited on every metric.
     */
    public function limits(): HasMany
    {
        return $this->hasMany(PackageLimit::class);
    }
}
