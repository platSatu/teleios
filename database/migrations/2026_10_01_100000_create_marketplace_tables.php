<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layanan "Marketplace" (1 Oktober 2026) -- toko marketplace milik pelanggan
 * yang dihubungkan ke branch, dimulai dari Lazada. Tabel dibuat umum
 * (kolom `provider`) supaya Shopee/TikTok Shop nanti memakai tabel yang sama.
 *
 * marketplace_shops
 *   - Satu toko hanya boleh terhubung ke SATU company/branch:
 *     unique(provider, external_shop_id). Menghubungkan ulang toko yang sama
 *     dari branch yang sama cukup memperbarui token-nya.
 *   - access_token/refresh_token disimpan terenkripsi (cast 'encrypted' di
 *     App\Models\MarketplaceShop), jadi kolomnya TEXT.
 *   - orders_synced_until = kursor sinkron pesanan (updated_at terakhir yang
 *     sudah diambil), supaya tiap sinkron hanya mengambil yang berubah.
 *
 * marketplace_orders
 *   - Ringkasan pesanan (bukan salinan lengkap) -- data pembeli yang disimpan
 *     seminimal mungkin (nama saja), sesuai aturan data marketplace.
 *   - unique(marketplace_shop_id, external_order_id) membuat sinkron
 *     idempotent: pesanan yang sama diambil 2x tetap 1 baris (upsert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_shops', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'mp_shop_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'mp_shop_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('connected_by_user_id')
                ->nullable()
                ->constrained(table: 'users', indexName: 'mp_shop_user_fk')
                ->nullOnDelete();

            $table->string('provider', 20); // lazada | (nanti: shopee, tiktok)
            $table->string('external_shop_id', 64); // Lazada: seller_id
            $table->string('name', 255);
            $table->string('country', 5)->nullable();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();

            $table->string('status', 20)->default('active'); // active | expired | disconnected

            $table->timestamp('orders_synced_until')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 500)->nullable();

            $table->timestamps();

            $table->unique(['provider', 'external_shop_id'], 'mp_shop_provider_external_unique');
            $table->index(['company_id', 'branch_office_id', 'provider'], 'mp_shop_scope_idx');
            $table->index(['provider', 'status'], 'mp_shop_status_idx');
        });

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('marketplace_shop_id')
                ->constrained(table: 'marketplace_shops', indexName: 'mp_order_shop_fk')
                ->cascadeOnDelete();

            // Didenormalisasi (pola sama dengan tabel tagihan_*) supaya daftar
            // pesanan per branch tidak perlu join ke marketplace_shops.
            $table->uuid('company_id');
            $table->uuid('branch_office_id');

            $table->string('external_order_id', 64);
            $table->string('order_number', 64)->nullable();
            $table->string('status', 40)->nullable();
            $table->string('buyer_name', 255)->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedInteger('item_count')->default(0);
            $table->string('payment_method', 100)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('external_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['marketplace_shop_id', 'external_order_id'], 'mp_order_shop_external_unique');
            $table->index(['company_id', 'branch_office_id', 'ordered_at'], 'mp_order_scope_idx');
            $table->index(['marketplace_shop_id', 'ordered_at'], 'mp_order_shop_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_shops');
    }
};
