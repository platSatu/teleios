<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebHeader — a slider of hero/header banners for
     * the (future) public web site homepage. Each row is one slide;
     * only status = active rows are shown, ordered by sort_order — same
     * "flat list + status filter" shape as web_faqs/web_features, plus
     * an explicit sort_order since a slider (unlike a flat FAQ list)
     * cares about display sequence.
     *
     * A slide's background is EITHER an uploaded video OR an uploaded
     * image, never both at once — background_type is the single source
     * of truth for which one is actually used (video|image). This is
     * deliberately a discriminator column rather than two independent
     * active/inactive toggles (which could contradict each other if
     * both were left "active"); the earlier draft of this schema had a
     * status_videos active/inactive column meant to play that role, but
     * "inactive" read as if the video itself were disabled rather than
     * "use the image instead" — background_type says that directly.
     * Enforced at the application level in Superadmin\Web\
     * HeaderController (assertHasBackgroundSource), same pattern
     * web_videos already uses for "at least one of videos/link_youtube".
     *
     * thumbnail_images is the poster frame shown before/while the video
     * loads — only meaningful when background_type = video, left null
     * for image slides.
     */
    public function up(): void
    {
        Schema::create('web_headers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('background_type', 10)->default('image'); // image | video — which of background_images/videos is actually rendered

            $table->string('videos')->nullable(); // uploaded video file, used when background_type = video
            $table->string('background_images')->nullable(); // uploaded image, used when background_type = image
            $table->string('thumbnail_images')->nullable(); // poster image for the video (background_type = video only)

            $table->string('text')->nullable(); // headline overlaid on the slide
            $table->text('descriptions')->nullable(); // supporting paragraph text

            $table->string('button_action', 20)->default('inactive'); // active | inactive — show/hide the CTA button
            $table->string('button_text')->nullable(); // CTA label, e.g. "Daftar Sekarang"
            $table->string('button_link')->nullable(); // CTA destination (URL or internal path)

            $table->unsignedInteger('sort_order')->default(0); // slide order in the rotation, ascending

            $table->string('status', 20)->default('active'); // active | inactive — whether this slide shows at all

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_headers');
    }
};
