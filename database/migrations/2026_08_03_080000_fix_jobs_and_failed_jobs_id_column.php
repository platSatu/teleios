<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 0001_01_01_000002_create_jobs_table originally defined `jobs.id` and
 * `failed_jobs.id` as a uuid primary key with no default value.
 * Illuminate\Queue\DatabaseQueue never generates an id itself when it
 * pushes a job — it relies entirely on the column auto-incrementing —
 * so every dispatch to the database queue (SendScheduledWaMessage,
 * SendMessageSequenceStep, SendAutoReplyMessage — i.e. Pesan Terjadwal,
 * Balasan Otomatis, and Auto Reply all together) failed silently with
 * MySQL error 1364 ("Field 'id' doesn't have a default value") before
 * ever reaching `queue:work`.
 *
 * Both tables only ever hold transient in-flight/debug data (nothing
 * worth preserving — every insert against the broken schema failed
 * anyway, so there's nothing real to migrate out), so this just drops
 * and recreates them with the auto-incrementing id the queue driver
 * actually expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('jobs');
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::dropIfExists('failed_jobs');
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::create('jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::dropIfExists('failed_jobs');
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }
};
