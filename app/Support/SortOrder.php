<?php

namespace App\Support;

use App\Helpers\Crud;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Urutan manual (kolom sort_order) untuk daftar yang diatur dengan tombol
 * naik/turun: section beranda, item section, Fitur, FAQ.
 */
final class SortOrder
{
    /**
     * Nomor urut berikutnya (paling bawah) dalam $scope.
     */
    public static function next(Builder $scope): int
    {
        return (int) $scope->max('sort_order') + 1;
    }

    /**
     * Tukar posisi $model dengan tetangganya ($direction 'up'|'down')
     * dalam $scope, sekaligus merapikan nomor urut jadi 1..n. Dikunci
     * (lockForUpdate) supaya dua klik bersamaan tidak saling menimpa.
     * Cache daftar CrudAdmin untuk model itu ikut di-invalidate.
     */
    public static function move(Model $model, string $direction, Builder $scope): void
    {
        DB::transaction(function () use ($model, $direction, $scope) {
            $ids = $scope->orderBy('sort_order')->orderBy('created_at')->lockForUpdate()->pluck($model->getKeyName())->all();
            $position = array_search($model->getKey(), $ids, true);
            $target = $direction === 'up' ? $position - 1 : $position + 1;

            if ($position === false || ! isset($ids[$target])) {
                return;
            }

            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];

            foreach ($ids as $index => $id) {
                $model->newQuery()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        Crud::invalidate($model::class);
    }
}
