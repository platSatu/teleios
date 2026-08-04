<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Crud
{
    private const PER_PAGE  = 10;
    private const CACHE_TTL = 60;
    private const CACHE_VER = 'crud:version:';

    // =========================================================
    // 1. GET ALL
    // =========================================================
    public static function getAll(
        string  $modelClass,
        array   $relations    = [],
        ?string $search       = null,
        array   $searchFields = ['name'],
        bool    $bypassCache  = false
    ): LengthAwarePaginator {
        $version  = self::getVersion($modelClass);
        $page     = request('page', 1);
        $cacheKey = self::key('all', $modelClass, $version, $search ?? '', $page);

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $relations, $search, $searchFields
        ) {
            $query = $modelClass::with($relations)
                ->orderBy('created_at', 'desc');

            if ($search && !empty($searchFields)) {
                $query->where(function ($q) use ($search, $searchFields) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }

            return $query->paginate(self::PER_PAGE);
        });
    }

    // =========================================================
    // 2. GET BY USER / COMPANY
    // =========================================================
    public static function getByUser(
        string  $modelClass,
        array   $relations      = [],
        ?string $search         = null,
        array   $searchFields   = ['name'],
        ?string $userColumn     = 'user_id',
        ?string $companyColumn  = 'company_id',
        bool    $bypassCache    = false
    ): LengthAwarePaginator {
        $user = Auth::user();

        if (!$user) {
            return self::emptyPaginator();
        }

        $userId    = $user->id;
        $companyId = $user->company_id ?? null;
        $version   = self::getVersion($modelClass);
        $page      = request('page', 1);

        // ✅ FIX: cast semua ke string agar tidak ada null
        $cacheKey = self::key(
            'user',
            $modelClass,
            (string) $version,
            $search       ?? '',
            (string) $page,
            (string) $userId,
            (string) ($companyId ?? '')
        );

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $relations, $search, $searchFields,
            $userColumn, $companyColumn, $userId, $companyId
        ) {
            $query = $modelClass::with($relations)
                ->orderBy('created_at', 'desc');

            $query->where(function ($q) use (
                $userColumn, $companyColumn, $userId, $companyId
            ) {
                if ($userColumn) {
                    $q->orWhere($userColumn, $userId);
                }
                if ($companyColumn && $companyId) {
                    $q->orWhere($companyColumn, $companyId);
                }
            });

            if ($search && !empty($searchFields)) {
                $query->where(function ($q) use ($search, $searchFields) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }

            return $query->paginate(self::PER_PAGE);
        });
    }

    // =========================================================
    // 3. CREATE
    // =========================================================
    public static function create(
        string    $modelClass,
        array     $data,
        bool      $autoUserId   = true,
        ?callable $beforeCreate = null,
        ?callable $afterCreate  = null
    ): Model {
        return DB::transaction(function () use (
            $modelClass, $data, $autoUserId, $beforeCreate, $afterCreate
        ) {
            if ($autoUserId && Auth::check()) {
                $data['user_id'] = $data['user_id'] ?? Auth::id();
            }

            if ($beforeCreate) {
                $data = $beforeCreate($data);
            }

            $model = $modelClass::lockForUpdate()->create($data);

            if ($afterCreate) {
                $afterCreate($model);
            }

            self::invalidate($modelClass);
            Log::info("[Crud::create] {$modelClass}", ['id' => $model->getKey()]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 4. GET BY ID
    // =========================================================
    public static function getById(
        string $modelClass,
        mixed  $id,
        array  $relations   = [],
        bool   $userScoped  = false,
        bool   $bypassCache = false
    ): Model {
        $version  = self::getVersion($modelClass);

        // ✅ FIX: cast $id ke string
        $cacheKey = self::key('id', $modelClass, (string) $version, (string) $id);

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $id, $relations, $userScoped
        ) {
            $query = $modelClass::with($relations);

            if ($userScoped && Auth::check()) {
                $query->where(function ($q) {
                    $user = Auth::user();
                    $q->where('user_id', $user->id);
                    if ($user->company_id) {
                        $q->orWhere('company_id', $user->company_id);
                    }
                });
            }

            return $query->findOrFail($id);
        });
    }

    // =========================================================
    // 5. UPDATE
    // =========================================================
    public static function update(
        string    $modelClass,
        mixed     $id,
        array     $data,
        bool      $userScoped   = true,
        ?callable $beforeUpdate = null,
        ?callable $afterUpdate  = null
    ): Model {
        return DB::transaction(function () use (
            $modelClass, $id, $data, $userScoped, $beforeUpdate, $afterUpdate
        ) {
            $query = $modelClass::lockForUpdate();

            if ($userScoped && Auth::check()) {
                $user = Auth::user();
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->company_id) {
                        $q->orWhere('company_id', $user->company_id);
                    }
                });
            }

            $model = $query->findOrFail($id);

            if ($beforeUpdate) {
                $data = $beforeUpdate($model, $data);
            }

            $model->fill($data)->save();

            if ($afterUpdate) {
                $afterUpdate($model);
            }

            self::invalidate($modelClass);

            // ✅ FIX: cast $id ke string
            Cache::forget(self::key('id', $modelClass, (string) self::getVersion($modelClass), (string) $id));

            Log::info("[Crud::update] {$modelClass}", ['id' => $id]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 6. DELETE
    // =========================================================
    public static function delete(
        string    $modelClass,
        mixed     $id,
        bool      $userScoped   = true,
        ?callable $beforeDelete = null
    ): bool {
        return DB::transaction(function () use (
            $modelClass, $id, $userScoped, $beforeDelete
        ) {
            $query = $modelClass::lockForUpdate();

            if ($userScoped && Auth::check()) {
                $user = Auth::user();
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->company_id) {
                        $q->orWhere('company_id', $user->company_id);
                    }
                });
            }

            $model = $query->findOrFail($id);

            if ($beforeDelete) {
                $beforeDelete($model);
            }

            $deleted = $model->delete();

            if ($deleted) {
                self::invalidate($modelClass);
                Log::info("[Crud::delete] {$modelClass}", ['id' => $id]);
            }

            return (bool) $deleted;
        });
    }

    // =========================================================
    // CACHE HELPERS
    // =========================================================

    public static function invalidate(string $modelClass): void
    {
        $key     = self::CACHE_VER . class_basename($modelClass);
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addDay());
    }

    private static function getVersion(string $modelClass): int
    {
        return (int) Cache::get(self::CACHE_VER . class_basename($modelClass), 0);
    }

    /**
     * ✅ FIX: Ganti type hint dari string ke mixed
     * agar bisa terima null, int, dll tanpa TypeError
     */
    private static function key(mixed ...$parts): string
    {
        // Cast semua ke string sebelum implode
        $stringParts = array_map(fn ($p) => (string) ($p ?? ''), $parts);
        return 'crud:' . md5(implode('|', $stringParts));
    }

    private static function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items:       collect(),
            total:       0,
            perPage:     self::PER_PAGE,
            currentPage: 1,
        );
    }
}