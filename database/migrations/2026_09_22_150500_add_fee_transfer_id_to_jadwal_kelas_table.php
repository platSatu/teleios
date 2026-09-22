<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lihat docblock create_pengajar_fee_transfers_table.php -- kolom ini
 * yang jadi penjaga idempotency fitur "Transfer Fee". NULL berarti
 * "belum pernah dibayarkan", terisi berarti sesi ini sudah termasuk
 * dalam satu App\Models\PengajarFeeTransfer tertentu dan TIDAK BOLEH
 * dihitung lagi di preview/eksekusi manapun setelahnya.
 *
 * restrictOnDelete (bukan cascade/null) -- konsisten dengan seluruh FK
 * finansial lain di proyek ini (LedgerEntry, PaymentTransaction, dst):
 * riwayat pembayaran tidak boleh menghilang diam-diam kalau baris
 * induknya somehow terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('jadwal_kelas', 'fee_transfer_id')) {
            return;
        }

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreignUuid('fee_transfer_id')
                ->nullable()
                ->after('persentase_pengajar')
                ->constrained('pengajar_fee_transfers')
                ->restrictOnDelete();

            $table->index(['pengajar_id', 'fee_transfer_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('jadwal_kelas', 'fee_transfer_id')) {
            return;
        }

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropIndex(['pengajar_id', 'fee_transfer_id']);
            $table->dropConstrainedForeignId('fee_transfer_id');
        });
    }
};
