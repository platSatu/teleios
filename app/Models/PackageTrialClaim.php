<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu nomor HP yang sudah pernah memakai trial. Dibuat
 * hanya oleh App\Services\Package\BranchSubscriptionService::claimTrial().
 */
class PackageTrialClaim extends Model
{
    use HasUuids;

    protected $table = 'package_trial_claims';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'phone',
        'user_id',
        'company_id',
        'branch_office_id',
        'package_id',
        'subscription_id',
    ];
}
