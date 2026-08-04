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
    // =========================================================
    // KONSTANTA
    // =========================================================
    private const PER_PAGE   = 10;
    private const CACHE_TTL  = 60; // detik — pendek agar realtime
    private const CACHE_VER  = 'crud:version:';

    // =========================================================
    // 1. GET ALL
    // Tampilkan semua data tanpa filter user_id (public)
    // - Urut created_at DESC (terbaru di atas)
    // - Paginate 10
    // - Support pencarian
    // - Selalu load relasi
    // =========================================================

    /**
     * @param  string        $modelClass   Nama class model, contoh: \App\Models\Post::class
     * @param  array         $relations    Relasi yang di-eager load, contoh: ['user', 'category']
     * @param  string|null   $search       Keyword pencarian
     * @param  array         $searchFields Kolom yang dicari, contoh: ['name', 'email']
     * @param  bool          $bypassCache  true = skip cache, ambil fresh dari DB
     */
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
    // Filter berdasarkan user_id ATAU company_id (salah satu atau keduanya)
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  array         $relations
     * @param  string|null   $search
     * @param  array         $searchFields
     * @param  string|null   $userColumn     Nama kolom user di tabel, default: 'user_id'
     * @param  string|null   $companyColumn  Nama kolom company di tabel, default: 'company_id'
     * @param  bool          $bypassCache
     */
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
        $cacheKey  = self::key('user', $modelClass, $version, $search, $page, $userId, $companyId);

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $relations, $search, $searchFields,
            $userColumn, $companyColumn, $userId, $companyId
        ) {
            $query = $modelClass::with($relations)
                ->orderBy('created_at', 'desc');

            // Filter: user_id ATAU company_id (salah satu atau keduanya)
            $query->where(function ($q) use (
                $userColumn, $companyColumn, $userId, $companyId
            ) {
                // Filter by user_id jika kolom ada
                if ($userColumn) {
                    $q->orWhere($userColumn, $userId);
                }

                // Filter by company_id jika kolom ada & user punya company
                if ($companyColumn && $companyId) {
                    $q->orWhere($companyColumn, $companyId);
                }
            });

            // Pencarian
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
    // Menggunakan DB::transaction + lockForUpdate
    // untuk mencegah race condition
    // =========================================================

    /**
     * @param  string    $modelClass
     * @param  array     $data           Data yang akan disimpan
     * @param  bool      $autoUserId     true = otomatis set user_id dari Auth
     * @param  callable|null $beforeCreate   Callback sebelum create (opsional)
     *                                   Terima parameter: $data (array)
     *                                   Return: $data yang sudah dimodifikasi
     * @param  callable|null $afterCreate    Callback setelah create (opsional)
     *                                   Terima parameter: $model (hasil create)
     */
    public static function create(
        string   $modelClass,
        array    $data,
        bool     $autoUserId   = true,
        ?callable $beforeCreate = null,
        ?callable $afterCreate  = null
    ): Model {
        return DB::transaction(function () use (
            $modelClass, $data, $autoUserId, $beforeCreate, $afterCreate
        ) {
            // user_id SELALU dipaksa dari user yang sedang login, bukan
            // sekadar default kalau belum ada di $data — sebelumnya
            // ditulis `$data['user_id'] ?? Auth::id()`, yang berarti kalau
            // payload kebetulan sudah membawa user_id sendiri (mis. field
            // form yang bocor), nilai itu dipakai apa adanya alih-alih
            // ditimpa. Itu celah spoofing: siapa pun bisa membuat record
            // atas nama user lain. Sekarang dipaksa mutlak.
            if ($autoUserId) {
                if (! Auth::check()) {
                    throw new \RuntimeException(
                        "Crud::create({$modelClass}) dipanggil dengan autoUserId=true tapi tidak ada user yang login."
                    );
                }
                $data['user_id'] = Auth::id();
            }

            // Callback sebelum create (modifikasi data jika perlu)
            if ($beforeCreate) {
                $data = $beforeCreate($data);
            }

            // Lock tabel untuk prevent race condition
            // Berguna untuk operasi yang butuh cek stok, saldo, kuota, dll
            $model = $modelClass::lockForUpdate()->create($data);

            // Callback setelah create (operasi tambahan jika perlu)
            if ($afterCreate) {
                $afterCreate($model);
            }

            // Invalidate cache agar data terbaru langsung muncul
            self::invalidate($modelClass);

            Log::info("[Crud::create] {$modelClass}", ['id' => $model->getKey()]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 3b. STORE (alias create, konvensi REST Laravel)
    // user_id SELALU dari user yang login — tidak bisa dimatikan,
    // beda dengan create() yang autoUserId-nya masih bisa di-set false
    // untuk kasus non-user-owned (mis. seeding/import dari job/queue).
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  array         $data
     * @param  callable|null $beforeCreate
     * @param  callable|null $afterCreate
     */
    public static function store(
        string    $modelClass,
        array     $data,
        ?callable $beforeCreate = null,
        ?callable $afterCreate  = null
    ): Model {
        return self::create(
            modelClass:   $modelClass,
            data:         $data,
            autoUserId:   true,
            beforeCreate: $beforeCreate,
            afterCreate:  $afterCreate,
        );
    }

    // =========================================================
    // 4. GET BY ID
    // =========================================================

    /**
     * @param  string   $modelClass
     * @param  mixed    $id
     * @param  array    $relations
     * @param  bool     $userScoped   true = pastikan record milik user yang login
     * @param  bool     $bypassCache
     */
    public static function getById(
        string $modelClass,
        mixed  $id,
        array  $relations   = [],
        bool   $userScoped  = false,
        bool   $bypassCache = false
    ): Model {
        $version  = self::getVersion($modelClass);
        $cacheKey = self::key('id', $modelClass, $version, $id);

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $id, $relations, $userScoped
        ) {
            $query = $modelClass::with($relations);

            // Scoping ke user yang login
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
    // Menggunakan DB::transaction + lockForUpdate
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  mixed         $id
     * @param  array         $data
     * @param  bool          $userScoped     true = hanya bisa update milik sendiri
     * @param  callable|null $beforeUpdate   Callback sebelum update
     * @param  callable|null $afterUpdate    Callback setelah update
     */
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
            // Lock record yang mau diupdate
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

            // Callback sebelum update
            if ($beforeUpdate) {
                $data = $beforeUpdate($model, $data);
            }

            $model->fill($data)->save();

            // Callback setelah update
            if ($afterUpdate) {
                $afterUpdate($model);
            }

            // Invalidate cache
            self::invalidate($modelClass);
            Cache::forget(self::key('id', $modelClass, self::getVersion($modelClass), $id));

            Log::info("[Crud::update] {$modelClass}", ['id' => $id]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 6. DELETE
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  mixed         $id
     * @param  bool          $userScoped
     * @param  callable|null $beforeDelete   Callback sebelum delete
     *                                       Bisa untuk hapus file, relasi, dll
     */
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

            // Callback sebelum delete (hapus file, relasi, dll)
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

    /**
     * Invalidate semua cache untuk model ini
     * dengan cara bump version number
     * — cara paling aman dan tidak butuh Redis SCAN
     */
    public static function invalidate(string $modelClass): void
    {
        $key = self::CACHE_VER . class_basename($modelClass);
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addDay());
    }

    /**
     * Ambil versi cache saat ini untuk model
     */
    /**
     * Public on purpose: CrudAdmin (app/Helpers/CrudAdmin.php) reuses this
     * same version counter so a write made through either helper
     * invalidates cached reads in both — they operate on the same
     * underlying tables, so the cache-busting has to be shared, even
     * though each helper's own result cache keys stay namespaced
     * separately (crud: vs crud_admin:).
     */
    public static function getVersion(string $modelClass): int
    {
        return (int) Cache::get(self::CACHE_VER . class_basename($modelClass), 0);
    }

    /**
     * Generate cache key yang unik
     */
    private static function key(mixed ...$parts): string
    {
        $stringParts = array_map(fn ($p) => (string) ($p ?? ''), $parts);
        return 'crud:' . md5(implode('|', $stringParts));
    }

    /**
     * Paginator kosong untuk return saat tidak ada user login
     */
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