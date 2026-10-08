<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Layanan "Payment Gateway" di katalog Category Application + menunya,
 * supaya superadmin bisa menjualnya sebagai paket (gate
 * 'active.package:Payment Gateway') dan owner bisa memberi menu ke role.
 * Idempotent, pola sama dengan seed_marketplace_application_catalog.
 */
return new class extends Migration
{
    private const CATEGORY_NAME = 'Payment Gateway';

    private const MENUS = [
        'payment-gateway.index' => 'Payment Gateway',
    ];

    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) = ?', [strtolower(self::CATEGORY_NAME)])
            ->value('id');

        if (! $categoryId) {
            $categoryId = (string) Str::uuid();

            DB::table('category_applications')->insert([
                'id' => $categoryId,
                'name' => self::CATEGORY_NAME,
                'description' => 'Terima pembayaran di website Anda sendiri lewat teleios (VA, QRIS, e-wallet).',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existing = DB::table('application_menus')->whereIn('route_name', array_keys(self::MENUS))->pluck('route_name')->all();

        foreach (self::MENUS as $routeName => $name) {
            if (in_array($routeName, $existing, true)) {
                continue;
            }

            DB::table('application_menus')->insert([
                'id' => (string) Str::uuid(),
                'category_application_id' => $categoryId,
                'name' => $name,
                'route_name' => $routeName,
                'sort_order' => 10,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja dibiarkan: category bisa sudah dipakai paket.
    }
};
