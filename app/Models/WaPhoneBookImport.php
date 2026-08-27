<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "Buku Telepon" bulk import run (Chat > Buku Telepon > Import) —
 * see the migration's docblock for the full status lifecycle and why
 * this table exists (moving App\Imports\PhoneBookImport off the HTTP
 * request and into App\Jobs\ProcessPhoneBookImport, a queued job).
 * Write-mostly from the company's perspective: created 'pending' by
 * App\Http\Controllers\Chat\PhoneBookController::import(), then only
 * ever updated by ProcessPhoneBookImport itself.
 */
class WaPhoneBookImport extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_phone_book_imports';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'user_id',
        'original_filename',
        'file_path',
        'allowed_category_ids',
        'allowed_branch_office_ids',
        'status',
        'total_created',
        'total_errors',
        'errors_detail',
        'skipped_sheets_detail',
        'failure_message',
        'processed_at',
    ];

    protected $casts = [
        'allowed_category_ids' => 'array',
        'allowed_branch_office_ids' => 'array',
        'errors_detail' => 'array',
        'skipped_sheets_detail' => 'array',
        'processed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
