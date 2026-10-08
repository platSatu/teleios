<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Payment Gateway" (8 Oktober 2026) -- website lain menerima
 * pembayaran lewat teleios, teleios yang memanggil Duitku. Berdiri sendiri
 * (tabel pg_*), tidak bercampur dengan Tagihan/Deposit/Keuangan.
 *
 * - pg_merchants   : satu website per company (key + secret + webhook + saldo).
 * - pg_fees        : tarif per metode bayar, diatur superadmin (dipotong dari dana masuk).
 * - pg_invoices    : pembayaran; unik (merchant, external_id) = anti dobel.
 * - pg_ledger      : buku saldo merchant (masuk dari invoice lunas, keluar dari penarikan).
 * - pg_webhook_logs: riwayat pengiriman webhook ke website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pg_merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('logo_url', 500)->nullable();
            $table->string('api_key', 64)->unique();
            $table->text('secret');
            $table->string('webhook_url', 500)->nullable();
            $table->json('allowed_domains')->nullable();
            $table->string('status', 20)->default('pending'); // pending | active | suspended
            $table->unsignedInteger('rate_limit_per_minute')->default(600);
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pg_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_method', 10)->unique(); // kode Duitku, mis. BC, M2, SP, OV
            $table->string('name', 100);
            $table->string('type', 20)->default('va'); // va | qris | ewallet | other
            $table->decimal('fee_percent', 6, 3)->default(0);
            $table->decimal('fee_flat', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pg_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pg_merchant_id')->constrained('pg_merchants')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->uuid('token')->unique();
            $table->string('order_number', 40)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->string('description', 255);
            $table->string('customer_name', 150);
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('return_url', 500)->nullable();
            $table->string('payment_method', 10)->nullable();
            $table->string('payment_name', 100)->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->string('va_number', 64)->nullable();
            $table->text('qr_string')->nullable();
            $table->text('payment_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending | paid | expired | failed | cancelled
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['pg_merchant_id', 'external_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['pg_merchant_id', 'created_at']);
        });

        Schema::create('pg_ledger', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pg_merchant_id')->constrained('pg_merchants')->cascadeOnDelete();
            $table->foreignUuid('pg_invoice_id')->nullable()->unique()->constrained('pg_invoices')->nullOnDelete();
            $table->string('type', 10); // credit | debit
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description', 255);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pg_merchant_id', 'created_at']);
        });

        Schema::create('pg_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pg_invoice_id')->constrained('pg_invoices')->cascadeOnDelete();
            $table->string('event', 40);
            $table->unsignedTinyInteger('attempt');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pg_invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pg_webhook_logs');
        Schema::dropIfExists('pg_ledger');
        Schema::dropIfExists('pg_invoices');
        Schema::dropIfExists('pg_fees');
        Schema::dropIfExists('pg_merchants');
    }
};
