<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaPhoneBookImport — one row per "Buku Telepon"
     * bulk import (Chat > Buku Telepon > Import), tracking the async job
     * behind it (App\Jobs\ProcessPhoneBookImport). Previously
     * App\Http\Controllers\Chat\PhoneBookController::import() ran
     * App\Imports\PhoneBookImport synchronously inside the HTTP request
     * and only ever reported the result via a one-shot session flash —
     * fine for a small file, but risky for the ~1000-row files this
     * feature is meant to support (request timeout on a slow host, and
     * the result was lost forever if the user navigated away or the
     * flash simply expired before they saw it). This table gives the
     * import a durable, per-company-visible result the user can check
     * back on any time after the job finishes, and lets the actual
     * parsing/inserting happen off the request entirely.
     *
     * `status` lifecycle: pending (row created, job not yet picked up) ->
     * processing (job running) -> done (job finished, whether or not
     * every row succeeded — see total_errors/errors_detail) OR failed
     * (an unexpected exception outside PhoneBookImport's own per-row
     * error handling — see failure_message).
     *
     * `errors_detail`/`skipped_sheets_detail` mirror
     * App\Imports\PhoneBookImport::$errors/$skippedSheets exactly (same
     * array shape) — see App\Jobs\ProcessPhoneBookImport::handle() for
     * where they're written.
     */
    public function up(): void
    {
        Schema::create('wa_phone_book_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Who uploaded the file — nullable + nullOnDelete so the
            // import's own history survives the uploader's account
            // later being removed, same convention as
            // wa_phone_book.created_by.
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('original_filename');

            // Where the uploaded file sits on the 'local' (private)
            // disk until the job has consumed it — see
            // App\Jobs\ProcessPhoneBookImport::handle().
            $table->string('file_path');

            // Snapshot, taken at upload time by
            // PhoneBookController::import(), of exactly which
            // wa_category_phone_book / branch_offices rows the
            // UPLOADING user was allowed to import against (same set
            // PhoneBookController::categoriesFor()/branchOfficesFor()
            // computes for the synchronous "Tambah Kontak" form,
            // including a branch-locked member's narrower scope) —
            // ProcessPhoneBookImport re-fetches these by id instead of
            // re-deriving the branch-lock rule itself, since a queued
            // job has no HTTP session/CompanyContext to derive it from.
            // For a company owner (who isn't branch-locked) this is
            // simply every category/branch row the company has, so the
            // job's lookup logic stays identical either way.
            $table->json('allowed_category_ids')->nullable();
            $table->json('allowed_branch_office_ids')->nullable();

            $table->string('status', 20)->default('pending'); // pending | processing | done | failed

            $table->unsignedInteger('total_created')->default(0);
            $table->unsignedInteger('total_errors')->default(0);

            // Same shape as App\Imports\PhoneBookImport::$errors: list of
            // {row, name, messages[]}.
            $table->json('errors_detail')->nullable();

            // Same shape as App\Imports\PhoneBookImport::$skippedSheets:
            // list of {sheet, row_count} — sheets rejected for having
            // more than PhoneBookImport::MAX_ROWS real data rows.
            $table->json('skipped_sheets_detail')->nullable();

            // Set only when the job itself blew up outside
            // PhoneBookImport's own per-row try/catch (e.g. the file
            // went missing from disk, a genuinely corrupt spreadsheet) —
            // see App\Jobs\ProcessPhoneBookImport's docblock.
            $table->text('failure_message')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_phone_book_imports');
    }
};
