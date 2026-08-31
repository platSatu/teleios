<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 integrasi Chat<->Jadwal -- permintaan ubah jadwal dari orang
 * tua/murid, ditangkap lewat chatbot flow WA yang sudah ada (Fitur #6,
 * lihat App\Services\Chat\ChatbotFlowService::executeAction()'s
 * ACTION_CREATE_JADWAL_RESCHEDULE_REQUEST) -- BUKAN otomatis mengubah
 * jadwal, cuma mencatat permintaan untuk direview & diproses manual
 * oleh staff (lihat App\Http\Controllers\Jadwal\
 * JadwalRescheduleRequestController), sesuai hasil diskusi ("wajib
 * approve staff").
 *
 * `jadwal_student_id`/`jadwal_kelas_id` NULLABLE & tidak diisi otomatis
 * dengan pasti -- flow chatbot cuma bisa menebak murid dari nomor HP
 * pengirim (bisa gagal/ambigu kalau satu nomor dipakai lebih dari satu
 * murid), dan TIDAK PERNAH bisa menebak baris Jadwal Kelas yang
 * spesifik (options di chatbot flow statis, tidak bisa didaftar
 * dinamis dari database -- lihat ChatbotFlowService's docblock) --
 * staff yang menghubungkan ke baris yang benar saat review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas_reschedule_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_student_id')
                ->nullable()
                ->constrained('jadwal_student')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_kelas_id')
                ->nullable()
                ->constrained('jadwal_kelas')
                ->nullOnDelete();

            $table->string('device_id', 36)->nullable();

            $table->string('chat_jid')->nullable();

            $table->string('requester_phone', 32)->nullable();

            // Transkrip pertanyaan+jawaban dari sesi chatbot flow --
            // lihat ChatbotFlowService::buildTranscript(). Sumber utama
            // yang dibaca staff saat review, karena isi permintaannya
            // (jadwal mana, mau pindah ke kapan) adalah teks bebas dari
            // orang tua, bukan data terstruktur.
            $table->text('detail_request');

            // 'pending' | 'approved' | 'rejected'
            $table->string('status', 20)->default('pending');

            $table->text('staff_notes')->nullable();

            $table->foreignUuid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas_reschedule_requests');
    }
};
