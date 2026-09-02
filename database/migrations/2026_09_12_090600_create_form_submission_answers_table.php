<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris jawaban per pertanyaan (form_content) dalam satu
 * form_submission -- lihat 2026_09_12_090500_create_form_submissions_table.php's
 * docblock untuk kenapa tabel ini ditambahkan di luar spek awal.
 *
 * `value` menampung jawaban teks (single_line/textarea) ATAU pilihan
 * (single_choice: 1 string; multiple_choice: JSON-encoded array of
 * string di kolom text yang sama, di-decode di sisi model/view --
 * tidak perlu kolom terpisah karena selalu salah satu dari value/
 * file_path yang terisi, tidak pernah dua-duanya).
 *
 * `file_path` -- path relatif di bawah public/form/submissions untuk
 * pertanyaan bertipe file_upload, ikut App\Helpers\JadwalImageUploader's
 * pola (bukan storage disk).
 *
 * form_content_id sengaja TIDAK punya FK constraint on-delete cascade
 * eksplisit ke arah nullOnDelete -- kalau pertanyaannya dihapus dari
 * builder, jawaban yang sudah masuk tetap harus bisa dilihat (histori),
 * jadi form_content_id dibiarkan nullable + nullOnDelete, bukan ikut
 * terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('form_submission_id')
                ->constrained(table: 'form_submissions', indexName: 'form_ans_submission_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_content_id')
                ->nullable()
                ->constrained(table: 'form_contents', indexName: 'form_ans_content_fk')
                ->nullOnDelete();

            $table->text('value')->nullable();
            $table->string('file_path')->nullable();

            $table->timestamps();

            $table->index(['form_submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_answers');
    }
};
