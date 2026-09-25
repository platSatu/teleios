<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket trial + catatan klaim trial.
 *
 * - packages.is_trial: ditandai superadmin di form Package. Paket trial
 *   boleh langsung diganti paket berbayar selama masih aktif (lihat
 *   App\Services\Package\BranchSubscriptionService).
 *
 * - package_trial_claims: trial hanya boleh SEKALI per nomor HP owner
 *   (users.handphone, dinormalisasi ke format 62...). phone UNIQUE --
 *   database sendiri yang menjamin dua checkout bersamaan tidak bisa
 *   sama-sama lolos. Sengaja tidak di-cascade saat user/paket dihapus:
 *   hapus akun lalu daftar ulang dengan nomor yang sama tetap tidak
 *   bisa trial lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('packages', 'is_trial')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('is_trial')->default(false)->after('is_featured');
            });
        }

        if (! Schema::hasTable('package_trial_claims')) {
            Schema::create('package_trial_claims', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('phone', 20)->unique();
                $table->uuid('user_id')->nullable()->index();
                $table->uuid('company_id')->nullable();
                $table->uuid('branch_office_id')->nullable();
                $table->uuid('package_id')->nullable();
                $table->uuid('subscription_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('package_trial_claims');

        if (Schema::hasColumn('packages', 'is_trial')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('is_trial');
            });
        }
    }
};
