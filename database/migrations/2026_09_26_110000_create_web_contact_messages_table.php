<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesan dari form Kontak fe-konexa (POST /api/frontend/contact-messages).
 * Disimpan dulu baru dikirim ke email Pengaturan Web, supaya pesan tidak
 * hilang kalau email gagal/masuk spam. Dibaca di Superadmin > Web >
 * Pesan Masuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_contact_messages')) {
            return;
        }

        Schema::create('web_contact_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('topic', 60);
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('status', 20)->default('new'); // new | replied | archived
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_contact_messages');
    }
};
