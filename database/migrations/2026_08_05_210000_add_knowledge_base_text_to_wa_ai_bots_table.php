<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the *extracted plain text* of the "Lampiran Knowledge Base"
 * upload (wa_ai_bots.attach_file_path), so App\Services\AiBot\
 * AiReplyGenerator can hand it to the AI provider as extra context
 * without re-reading/re-parsing the source file (pdf/docx/txt) on every
 * single incoming message. Nullable/longText: extraction can fail (a
 * scanned/image-only PDF has no extractable text) or simply not have
 * been run yet on an older upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->longText('knowledge_base_text')->nullable()->after('attach_file_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('wa_ai_bots', function (Blueprint $table) {
            $table->dropColumn('knowledge_base_text');
        });
    }
};
