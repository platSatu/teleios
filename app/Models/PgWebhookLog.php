<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class PgWebhookLog extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $table = 'pg_webhook_logs';

    protected $fillable = ['pg_invoice_id', 'event', 'attempt', 'http_status', 'error'];
}
