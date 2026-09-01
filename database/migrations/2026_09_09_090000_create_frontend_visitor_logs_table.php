<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\FrontendVisitorLog — satu baris per kunjungan ke
 * halaman publik fe-konexa (beranda/artikel/syarat-dan-ketentuan/
 * video/kontak). Bukan HistoryUserLogin: itu sesi login akun yang
 * SUDAH terdaftar (terikat users.id), ini pengunjung situs marketing
 * yang KEBANYAKAN belum punya akun sama sekali -- makanya sengaja
 * tanpa foreign key ke `users` sama sekali.
 *
 * Ditulis dari App\Http\Controllers\Api\Frontend\VisitorLogController
 * lewat POST /api/frontend/visitor-log, dipanggil server-to-server
 * oleh fe-konexa (App\Http\Middleware\LogVisitorMiddleware di app itu)
 * pakai secret X-API-KEY yang sama dengan endpoint /api/frontend/*
 * lain -- lihat App\Http\Middleware\VerifyFrontendApiKey. Endpoint ini
 * TIDAK bisa dipanggil sembarangan dari browser pengunjung, cuma dari
 * server fe-konexa yang tahu kuncinya.
 *
 * browser/browser_version/os/device_type diparse dari user_agent di
 * sisi Teleios sendiri (pakai jenssegers/agent) begitu baris ini
 * ditulis -- bukan dipercaya mentah dari fe-konexa -- supaya logika
 * parsing-nya cuma ada di satu tempat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_visitor_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Cookie anonim dari fe-konexa (bukan akun) -- dipakai
            // untuk bedakan "1 orang buka 5x" vs "5 orang beda buka
            // 1x", tanpa pernah tahu identitas aslinya.
            $table->string('visitor_id', 64);

            $table->string('ip_address', 45); // cukup untuk IPv6
            $table->text('user_agent')->nullable();

            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 30)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('device_type', 20)->nullable(); // desktop | mobile | tablet | bot

            $table->string('path', 255);
            $table->string('referrer', 255)->nullable();

            $table->timestamp('visited_at');

            $table->timestamps();

            $table->index('visited_at');
            $table->index('visitor_id');
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_visitor_logs');
    }
};
