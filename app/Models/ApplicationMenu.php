<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Superadmin-managed menu naming per Category Application — see
 * Superadmin\ApplicationMenuController. `user_id` is nullable, same
 * reasoning as CategoryApplication/Package: this is catalog data, not
 * necessarily tied to one specific user.
 *
 * `route_name`/`icon`/`sort_order`/`parent_id` make this catalog
 * navigable rather than purely descriptive: resources/views/layouts/
 * partials/menu.blade.php renders the sidebar's dynamic Chat section
 * from this table, filtered per the logged-in user's resolved
 * CompanyRole via App\Models\CompanyRoleMenu (see
 * App\Services\Company\CompanyContextResolver). `route_name` is null
 * for entries that are just a grouping label with no page of their own.
 */
class ApplicationMenu extends Model
{
    protected $table = 'application_menus';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'category_application_id',
        'parent_id',
        'name',
        'route_name',
        'icon',
        'sort_order',
        'description',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $menu) {
            if (empty($menu->id)) {
                $menu->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoryApplication()
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
