<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Superadmin-managed "Syarat dan Ketentuan" (Terms & Conditions)
     * entries — same flat shape as `web_faqs` (App\Models\WebFaq /
     * Superadmin\Web\FaqController): one `name` + one long-text
     * `descriptions`, no category/slug. Deliberately kept as a multi-row
     * CRUD table (not a single "settings" row) to match every other Web
     * Content resource in this app (Meta Tags, Articles, FAQ, Videos all
     * follow this same list-of-entries shape) rather than inventing a
     * one-off singleton pattern that doesn't exist anywhere else here.
     *
     * `status` picks which single row is "the current live version" shown
     * on the register page (see resources/views/auth/register.blade.php)
     * — only one row is expected to be 'active' at a time, enforced at
     * the application layer (TermConditionController), not a DB
     * constraint, same as every other status column in this app.
     *
     * `users.terms_id` (added by the next migration) references a row
     * here, which is why this migration both creates AND seeds a
     * starting row: a fresh install needs at least one 'active' row to
     * exist the moment the register form goes live, otherwise there's
     * nothing to link a newly-registered user's acceptance to.
     */
    public function up(): void
    {
        Schema::create('web_term_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->longText('descriptions');
            $table->string('status', 20)->default('active'); // active | inactive
            $table->timestamps();
        });

        DB::table('web_term_conditions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => null,
            'name' => 'Syarat dan Ketentuan',
            'descriptions' => "Dengan mendaftar dan menggunakan layanan ini, Anda menyetujui hal-hal berikut:\n\n"
                ."1. Anda bertanggung jawab atas kebenaran data yang Anda daftarkan (nama, email, dan nomor WhatsApp).\n"
                ."2. Nomor WhatsApp yang didaftarkan hanya digunakan untuk keperluan komunikasi layanan (verifikasi, notifikasi, dan dukungan pelanggan).\n"
                ."3. Anda tidak akan menyalahgunakan layanan ini untuk mengirim pesan spam, konten ilegal, atau melanggar ketentuan penggunaan WhatsApp.\n"
                ."4. Kami berhak menangguhkan atau menonaktifkan akun yang terbukti melanggar ketentuan ini.\n"
                ."5. Syarat dan Ketentuan ini dapat diperbarui sewaktu-waktu; perubahan akan berlaku efektif sejak dipublikasikan.\n\n"
                .'Silakan hubungi tim kami apabila ada pertanyaan mengenai ketentuan ini.',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('web_term_conditions');
    }
};
