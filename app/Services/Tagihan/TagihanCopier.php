<?php

namespace App\Services\Tagihan;

use App\Models\Tagihan;
use App\Models\TagihanCategory;
use App\Models\TagihanPenerima;
use Illuminate\Support\Facades\DB;

/**
 * Tombol "Copy" di Kategori Tagihan & Tagihan (7 Oktober 2026). Salinan
 * adalah baris biasa (bisa diedit & dihapus seperti biasa), dibuat dalam
 * SATU transaksi supaya tidak pernah setengah jadi.
 *
 * Kategori: semua pengaturan (denda, prefix invoice, notifikasi, deskripsi,
 * status) + aturan pengingat + tier denda lama + daftar langganan pelanggan
 * (beserta nominal_override & status-nya). Tagihan (invoice) lama TIDAK ikut
 * -- itu histori milik kategori asal.
 *
 * Tagihan: semua isian (kategori, nominal, jatuh tempo, denda, template WA;
 * status salinan selalu aktif) + aturan pengingat + daftar penerima dengan nominal masing-masing.
 * Penerima disalin sebagai invoice BARU: belum_bayar, denda 0, token/nomor
 * order/invoice baru (digenerate TagihanPenerima::boot), tanpa riwayat bayar.
 * Penerima yang dibatalkan tidak ikut. Link WA TIDAK dikirim otomatis --
 * admin biasanya mengubah nama/jatuh tempo salinan dulu.
 */
class TagihanCopier
{
    public function copyCategory(TagihanCategory $source): TagihanCategory
    {
        return DB::transaction(function () use ($source) {
            // Kunci baris asal supaya isi yang disalin tidak berubah di tengah jalan.
            $source = TagihanCategory::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $source->load(['reminderRuleTemplates', 'dendaTiers', 'categoryPelanggan']);

            $copy = $source->replicate();
            // Salinan = kategori biasa, tidak ikut tertaut ke Grade (kunci unik grade+branch).
            $copy->jadwal_grade_id = null;
            $copy->name = $this->uniqueName($source->name, fn ($name) => TagihanCategory::where('company_id', $source->company_id)
                ->where('branch_office_id', $source->branch_office_id)
                ->where('name', $name)
                ->exists());
            $copy->save();

            foreach ($source->reminderRuleTemplates as $rule) {
                $copy->reminderRuleTemplates()->create($rule->only(['remind_value', 'remind_unit']));
            }

            foreach ($source->dendaTiers as $tier) {
                $copy->dendaTiers()->create($tier->only(['urutan', 'mulai_hari_ke', 'sampai_hari_ke', 'tipe', 'nilai', 'frekuensi_flat']));
            }

            foreach ($source->categoryPelanggan as $langganan) {
                $copy->categoryPelanggan()->create($langganan->only(['tagihan_pelanggan_id', 'nominal_override', 'status']));
            }

            return $copy;
        });
    }

    public function copyTagihan(Tagihan $source): Tagihan
    {
        return DB::transaction(function () use ($source) {
            $source = Tagihan::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $source->load(['reminderRules', 'penerima']);

            $copy = $source->replicate();
            $copy->name = $this->uniqueName($source->name, fn ($name) => Tagihan::where('tagihan_category_id', $source->tagihan_category_id)
                ->where('name', $name)
                ->exists());
            $copy->status = Tagihan::STATUS_ACTIVE;
            $copy->save();

            foreach ($source->reminderRules as $rule) {
                $copy->reminderRules()->create($rule->only(['remind_value', 'remind_unit']));
            }

            foreach ($source->penerima as $penerima) {
                if ($penerima->status === TagihanPenerima::STATUS_DIBATALKAN) {
                    continue;
                }

                TagihanPenerima::create([
                    'tagihan_id' => $copy->id,
                    'tagihan_pelanggan_id' => $penerima->tagihan_pelanggan_id,
                    'company_id' => $copy->company_id,
                    'branch_office_id' => $copy->branch_office_id,
                    'amount' => $penerima->amount,
                    'status' => TagihanPenerima::STATUS_BELUM_BAYAR,
                ]);
            }

            return $copy;
        });
    }

    /** "Nama (Salinan)", lalu "(Salinan 2)", dst. kalau sudah dipakai. */
    private function uniqueName(string $name, callable $exists): string
    {
        $base = mb_substr($name, 0, 240).' (Salinan)';
        $candidate = $base;

        for ($i = 2; $exists($candidate); $i++) {
            $candidate = mb_substr($name, 0, 235)." (Salinan {$i})";
        }

        return $candidate;
    }
}
