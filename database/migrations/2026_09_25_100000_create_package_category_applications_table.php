<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu paket bisa mencakup BEBERAPA layanan (category_applications),
 * mis. "Lengkap" = Chat + Form + Jadwal + Tagihan, "Chat + Tagihan", dst.
 * Sebelumnya packages.category_application_id cuma bisa satu.
 *
 * Kolom lama packages.category_application_id TIDAK dihapus (NOT NULL,
 * masih dibaca kode lama) -- tetap diisi sebagai "category utama". Setiap
 * paket yang sudah ada di-backfill jadi satu baris pivot dari category
 * utamanya, jadi cakupan paket lama tidak berubah sama sekali.
 *
 * Idempotent: aman di-retry kalau sempat gagal di tengah jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_category_applications')) {
            Schema::create('package_category_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('package_id')->constrained('packages')->cascadeOnDelete();
                $table->foreignUuid('category_application_id')->constrained('category_applications')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['package_id', 'category_application_id'], 'pkg_cat_app_unique');
            });
        }

        $now = now();

        DB::table('packages')
            ->whereNotNull('category_application_id')
            ->orderBy('id')
            ->select(['id', 'category_application_id'])
            ->chunk(200, function ($packages) use ($now) {
                DB::table('package_category_applications')->insertOrIgnore(
                    $packages->map(fn ($p) => [
                        'package_id' => $p->id,
                        'category_application_id' => $p->category_application_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_category_applications');
    }
};
