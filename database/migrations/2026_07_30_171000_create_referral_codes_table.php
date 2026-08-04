<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // 1:1 with users, same shape as wallets.user_id — every user
            // gets exactly one referral code, auto-created alongside
            // their Wallet (see App\Models\User::boot()).
            $table->foreignUuid('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('code')->unique();

            // Commission percentage this user earns when someone signs
            // up using their code. Default 20%, editable by superadmin.
            $table->decimal('percentage', 5, 2)->default(20.00);

            // active | blocked — superadmin can block a code so it can
            // no longer be used, without deleting the record/history.
            $table->string('status', 20)->default('active');

            $table->timestamps();
        });

        // Backfill: users created before this migration ran don't have a
        // referral code yet from App\Models\User::boot() (that hook only
        // fires for NEW inserts going forward). Done here with raw
        // DB/query-builder calls rather than the Eloquent models, so this
        // migration keeps working correctly even if App\Models\User or
        // App\Models\ReferralCode change shape later.
        $now = now();

        DB::table('users')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('referral_codes')
                    ->whereColumn('referral_codes.user_id', 'users.id');
            })
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function ($user) use ($now) {
                DB::table('referral_codes')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'code' => $this->generateUniqueCode($user->name),
                    'percentage' => 20.00,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }

    /**
     * Same code shape as App\Models\ReferralCode::generateUniqueCode() —
     * first name (alnum only, uppercased, capped at 6 chars) + 4 random
     * uppercase chars, re-rolled until it doesn't collide.
     */
    private function generateUniqueCode(?string $name): string
    {
        $firstName = trim(strtok((string) $name, ' '));
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $firstName) ?: 'USER');
        $base = substr($base, 0, 6) ?: 'USER';

        do {
            $code = $base . strtoupper(Str::random(4));
        } while (DB::table('referral_codes')->where('code', $code)->exists());

        return $code;
    }
};
