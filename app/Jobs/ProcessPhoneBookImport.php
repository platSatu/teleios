<?php

namespace App\Jobs;

use App\Imports\PhoneBookImport;
use App\Models\BranchOffice;
use App\Models\WaCategoryPhoneBook;
use App\Models\WaPhoneBookImport;
use App\Services\Crm\CustomerIdentityService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Runs one App\Models\WaPhoneBookImport off the HTTP request — see that
 * model's migration docblock for why (request timeout risk on a ~1000
 * row file, and a lost-forever result if the old session-flash message
 * expired before the user saw it). Dispatched by
 * App\Http\Controllers\Chat\PhoneBookController::import() with just the
 * record's id (same "pass an id, not a model/file, into a queued job"
 * convention App\Jobs\SendScheduledWaMessage follows), so a retried job
 * always re-reads current state from the database instead of risking a
 * stale serialized model/file handle.
 *
 * $tries = 1 deliberately: App\Imports\PhoneBookImport already turns
 * every per-row problem into an $errors entry instead of throwing, so an
 * exception that reaches this job's own try/catch below means the WHOLE
 * import couldn't run (corrupt/unreadable file, disk I/O failure, etc.)
 * — not a "some rows failed" case (that's a normal STATUS_DONE result).
 * That's not the kind of transient condition (a device briefly offline,
 * a rate limit) SendScheduledWaMessage's retry/backoff exists for;
 * automatically retrying it would just burn a queue worker slot
 * repeating the same failure, so this records it once via the catch
 * block (or, for anything unexpected enough to escape that, via
 * failed() below) instead.
 */
class ProcessPhoneBookImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private string $importId)
    {
    }

    public function handle(CustomerIdentityService $customerIdentity, PackageLimitService $packageLimits): void
    {
        $import = WaPhoneBookImport::find($this->importId);

        if (! $import) {
            // Record gone (nothing in this app deletes these rows in
            // normal use, but a job with nothing left to report against
            // has nothing useful left to do).
            return;
        }

        $company = $import->company;

        if (! $company) {
            $import->forceFill([
                'status' => WaPhoneBookImport::STATUS_FAILED,
                'failure_message' => 'Perusahaan pemilik import ini sudah tidak ada.',
                'processed_at' => now(),
            ])->save();

            return;
        }

        $import->forceFill(['status' => WaPhoneBookImport::STATUS_PROCESSING])->save();

        if (! Storage::disk('local')->exists($import->file_path)) {
            $import->forceFill([
                'status' => WaPhoneBookImport::STATUS_FAILED,
                'failure_message' => 'File yang diupload tidak ditemukan lagi di server.',
                'processed_at' => now(),
            ])->save();

            return;
        }

        // Re-fetched by id from the snapshot PhoneBookController::import()
        // took at upload time (see the migration's docblock for
        // allowed_category_ids/allowed_branch_office_ids) — this job has
        // no HTTP session/CompanyContext of its own to re-derive a
        // branch-locked member's narrower scope from, so it trusts the
        // exact set that was valid for the uploading user when they
        // uploaded the file.
        $categories = WaCategoryPhoneBook::where('company_id', $company->id)
            ->whereIn('id', $import->allowed_category_ids ?? [])
            ->get();

        $branchOffices = BranchOffice::where('company_id', $company->id)
            ->whereIn('id', $import->allowed_branch_office_ids ?? [])
            ->get();

        try {
            $importer = new PhoneBookImport(
                $company,
                $categories,
                $branchOffices,
                $import->user_id,
                $customerIdentity,
                $packageLimits,
            );

            Excel::import($importer, Storage::disk('local')->path($import->file_path));

            $import->forceFill([
                'status' => WaPhoneBookImport::STATUS_DONE,
                'total_created' => count($importer->created),
                'total_errors' => count($importer->errors),
                'errors_detail' => $importer->errors,
                'skipped_sheets_detail' => $importer->skippedSheets,
                'processed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('ProcessPhoneBookImport: import failed', [
                'import_id' => $import->id,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            $import->forceFill([
                'status' => WaPhoneBookImport::STATUS_FAILED,
                'failure_message' => 'Import gagal diproses karena kesalahan tak terduga. Silakan coba upload ulang filenya.',
                'processed_at' => now(),
            ])->save();

            return;
        } finally {
            // The uploaded file is only ever needed for this one run —
            // remove it either way (success or the failure path above)
            // so private storage doesn't keep every import's file
            // forever. Best-effort: a delete failure here shouldn't
            // affect the already-recorded result above.
            try {
                Storage::disk('local')->delete($import->file_path);
            } catch (Throwable $e) {
                // Nothing more to do — the result is already saved.
            }
        }
    }

    /**
     * Called once $tries (=1) is exhausted for anything that escaped the
     * try/catch in handle() itself — e.g. the database being unreachable
     * when marking the row 'processing', not a PhoneBookImport/Excel
     * problem (those are always caught above). Deliberately doesn't put
     * $e->getMessage() into the company-visible failure_message (that
     * could be a raw internal exception string) — the real detail goes
     * to the log instead.
     */
    public function failed(Throwable $e): void
    {
        Log::error('ProcessPhoneBookImport: job failed outside its own try/catch', [
            'import_id' => $this->importId,
            'error' => $e->getMessage(),
        ]);

        $import = WaPhoneBookImport::find($this->importId);

        if (! $import || $import->isFinished()) {
            return;
        }

        $import->forceFill([
            'status' => WaPhoneBookImport::STATUS_FAILED,
            'failure_message' => 'Import gagal diproses karena kesalahan tak terduga di server.',
            'processed_at' => now(),
        ])->save();
    }
}
