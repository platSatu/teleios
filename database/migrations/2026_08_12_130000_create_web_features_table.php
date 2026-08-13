<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WebFeature — a flat list of highlighted product/
     * service features (name, description, image) for the (future)
     * public web site. Same shape as create_web_faqs_table. Exposed
     * publicly (status = active only) via GET /api/frontend/features —
     * see App\Http\Controllers\Api\Frontend\FeatureController.
     */
    public function up(): void
    {
        Schema::create('web_features', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('images')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_features');
    }
};
