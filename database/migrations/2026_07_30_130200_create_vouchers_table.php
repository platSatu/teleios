<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Required (unlike category_applications/packages): every
            // voucher belongs to exactly one user. Cascade on delete —
            // a voucher issued to a user has no meaning once that user
            // is gone.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Unique: two vouchers with the same code would make
            // redemption ambiguous.
            $table->string('kode_voucher')->unique();

            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
