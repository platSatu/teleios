<?php

namespace App\Helpers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CrudAdmin is the superadmin-only counterpart to Crud (app/Helpers/Crud.php).
 *
 * Every method here first checks the caller is actually a superadmin
 * (user_type === 'SUPERADMIN') — if not, it aborts with 403. Once past
 * that gate, it deliberately does NOT apply the per-user ownership
 * restrictions Crud enforces: a superadmin can list/create/update/delete
 * ANY record regardless of who owns it, since that's the entire point of
 * an owner/admin-level tool (managing other users' packages, wallets,
 * vouchers, etc).
 *
 * Because that's a lot of power concentrated in one class, every write
 * (store/update/delete) is recorded into the immutable `audit_logs`
 * table via writeAudit() — who (actor_id), what (action + model +
 * record id), when, from where (ip/user agent), and the before/after
 * state. App\Models\AuditLog itself refuses updates and deletes at the
 * model level, so once written an entry can't be quietly edited away.
 * This is the kind of traceability an ISO 27001 review or a banking-style
 * audit would expect for anything with superadmin-level reach — it's not
 * optional bookkeeping, it's the reason this class is allowed to bypass
 * ownership checks at all.
 *
 * Usage:
 *   CrudAdmin::getAll(Voucher::class);
 *   CrudAdmin::store(Voucher::class, $validated);
 *   CrudAdmin::update(Voucher::class, $id, $validated);
 *   CrudAdmin::delete(Voucher::class, $id);
 */
class CrudAdmin
{
    private const CACHE_TTL = 60; // detik — pendek agar realtime
    private const DEFAULT_PER_PAGE = 20;

    // =========================================================
    // GUARD
    // =========================================================

    /**
     * Every public method calls this first. There's no degraded/filtered
     * mode for a non-superadmin caller — CrudAdmin is superadmin-only or
     * it's a 403, full stop.
     */
    private static function assertSuperadmin(): void
    {
        $user = Auth::user();

        abort_unless(
            $user && $user->user_type === 'SUPERADMIN',
            403,
            'Aksi ini khusus untuk superadmin.'
        );
    }

    // =========================================================
    // 1. GET ALL — tanpa batasan kepemilikan sama sekali
    // =========================================================

    /**
     * @param  string       $modelClass
     * @param  array        $relations
     * @param  string|null  $search
     * @param  array        $searchFields
     * @param  int          $perPage
     * @param  bool         $bypassCache
     */
    public static function getAll(
        string  $modelClass,
        array   $relations    = [],
        ?string $search       = null,
        array   $searchFields = ['name'],
        int     $perPage      = self::DEFAULT_PER_PAGE,
        bool    $bypassCache  = false
    ): LengthAwarePaginator {
        self::assertSuperadmin();

        // Shares Crud's version counter (see Crud::getVersion) so a write
        // made through either helper busts cached reads in both — they
        // read/write the same tables, just with different authorization
        // rules layered on top.
        $version  = Crud::getVersion($modelClass);
        $page     = request('page', 1);
        $cacheKey = self::key('all', $modelClass, $version, $search ?? '', $page, $perPage);

        if ($bypassCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $modelClass, $relations, $search, $searchFields, $perPage
        ) {
            $query = $modelClass::with($relations)->orderBy('created_at', 'desc');

            if ($search && ! empty($searchFields)) {
                $query->where(function ($q) use ($search, $searchFields) {
                    foreach ($searchFields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            }

            return $query->paginate($perPage);
        });
    }

    // =========================================================
    // 1b. FIND — satu record, dipakai untuk mengisi form edit.
    // Sengaja tetap lewat CrudAdmin (bukan Model::findOrFail langsung
    // di controller) supaya SEMUA akses data admin — baca maupun tulis
    // — melewati gerbang superadmin yang sama, bukan cuma yang nulis.
    // =========================================================

    /**
     * @param  string             $modelClass
     * @param  mixed              $id
     * @param  array<int, string> $relations
     */
    public static function find(string $modelClass, mixed $id, array $relations = []): Model
    {
        self::assertSuperadmin();

        return $modelClass::with($relations)->findOrFail($id);
    }

    // =========================================================
    // 2. STORE
    // Beda dari Crud::store(): admin BOLEH menetapkan kepemilikan
    // record ke user manapun secara eksplisit lewat $data (mis. bikin
    // voucher/wallet adjustment atas nama user lain) — tidak dipaksa
    // ke Auth::id() sendiri. Siapa yang benar-benar melakukan aksi ini
    // tetap tercatat lewat writeAudit(), terlepas dari record itu
    // "milik" siapa.
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
        self::assertSuperadmin();

        return DB::transaction(function () use ($modelClass, $data, $beforeCreate, $afterCreate) {
            if ($beforeCreate) {
                $data = $beforeCreate($data);
            }

            $model = $modelClass::lockForUpdate()->create($data);

            if ($afterCreate) {
                $afterCreate($model);
            }

            Crud::invalidate($modelClass);
            self::writeAudit('create', $modelClass, (string) $model->getKey(), null, $model->toArray());

            Log::info("[CrudAdmin::store] {$modelClass}", ['id' => $model->getKey(), 'by' => Auth::id()]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 3. UPDATE — tanpa batasan kepemilikan
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  mixed         $id
     * @param  array         $data
     * @param  callable|null $beforeUpdate
     * @param  callable|null $afterUpdate
     */
    public static function update(
        string    $modelClass,
        mixed     $id,
        array     $data,
        ?callable $beforeUpdate = null,
        ?callable $afterUpdate  = null
    ): Model {
        self::assertSuperadmin();

        return DB::transaction(function () use ($modelClass, $id, $data, $beforeUpdate, $afterUpdate) {
            $model  = $modelClass::lockForUpdate()->findOrFail($id);
            $before = $model->toArray();

            if ($beforeUpdate) {
                $data = $beforeUpdate($model, $data);
            }

            $model->fill($data)->save();

            if ($afterUpdate) {
                $afterUpdate($model);
            }

            Crud::invalidate($modelClass);
            self::writeAudit('update', $modelClass, (string) $model->getKey(), $before, $model->toArray());

            Log::info("[CrudAdmin::update] {$modelClass}", ['id' => $id, 'by' => Auth::id()]);

            return $model->fresh();
        });
    }

    // =========================================================
    // 4. DELETE — tanpa batasan kepemilikan
    // =========================================================

    /**
     * @param  string        $modelClass
     * @param  mixed         $id
     * @param  callable|null $beforeDelete
     */
    public static function delete(
        string    $modelClass,
        mixed     $id,
        ?callable $beforeDelete = null
    ): bool {
        self::assertSuperadmin();

        return DB::transaction(function () use ($modelClass, $id, $beforeDelete) {
            $model  = $modelClass::lockForUpdate()->findOrFail($id);
            $before = $model->toArray();

            if ($beforeDelete) {
                $beforeDelete($model);
            }

            $deleted = $model->delete();

            if ($deleted) {
                Crud::invalidate($modelClass);
                self::writeAudit('delete', $modelClass, (string) $id, $before, null);
                Log::info("[CrudAdmin::delete] {$modelClass}", ['id' => $id, 'by' => Auth::id()]);
            }

            return (bool) $deleted;
        });
    }

    // =========================================================
    // AUDIT TRAIL
    // =========================================================

    /**
     * Records who (Auth::id()), what (action + model + record id), and
     * the before/after state into the immutable audit_logs table.
     *
     * Failure to write the audit entry does NOT roll back the underlying
     * data change (audit_logs is logging, not a data-integrity gate) —
     * but it's logged loudly to the application log if it fails, since a
     * missing audit trail entry for a superadmin action is itself worth
     * knowing about.
     */
    private static function writeAudit(
        string $action,
        string $modelClass,
        string $entityId,
        ?array $old,
        ?array $new
    ): void {
        try {
            AuditLog::create([
                'actor_type'  => Auth::user() ? Auth::user()::class : null,
                'actor_id'    => Auth::id(),
                'action'      => $action,
                'entity_type' => $modelClass,
                'entity_id'   => $entityId,
                'old_value'   => $old,
                'new_value'   => $new,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('[CrudAdmin] gagal menulis audit log', [
                'action'    => $action,
                'model'     => $modelClass,
                'entity_id' => $entityId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // =========================================================
    // CACHE KEY HELPER
    // (versi/invalidation memakai Crud::getVersion()/Crud::invalidate()
    // supaya kedua helper tetap sinkron — lihat catatan di getAll())
    // =========================================================

    private static function key(mixed ...$parts): string
    {
        $stringParts = array_map(fn ($p) => (string) ($p ?? ''), $parts);

        return 'crud_admin:' . md5(implode('|', $stringParts));
    }
}
