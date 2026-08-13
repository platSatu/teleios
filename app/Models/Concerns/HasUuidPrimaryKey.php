<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a random UUID primary key instead of Eloquent's default
 * auto-increment int — the same `$keyType = 'string'; $incrementing =
 * false;` + `creating()` boilerplate that used to be hand-copied into
 * every model's own boot() (WaContact, WaPhoneBook, WaConversation,
 * WaChatbotFlow, WaOptOut, etc.). Extracted here so new models (starting
 * with WaCustomer) don't repeat it, and so the one place that generates
 * these IDs is easy to find.
 *
 * Uses Eloquent's trait auto-boot convention — bootHasUuidPrimaryKey()
 * and initializeHasUuidPrimaryKey() are picked up and called
 * automatically for any model that `use`s this trait, no manual wiring
 * needed in the model itself.
 */
trait HasUuidPrimaryKey
{
    public static function bootHasUuidPrimaryKey(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuidPrimaryKey(): void
    {
        $this->keyType = 'string';
        $this->incrementing = false;
    }
}
