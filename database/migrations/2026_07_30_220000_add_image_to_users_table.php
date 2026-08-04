<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile photo path (relative to the `public` disk, e.g.
     * "avatars/{user_id}.jpg") — see App\Http\Controllers\
     * ProfileController::update(). Nullable: falls back to the default
     * placeholder avatar in the header until a user uploads one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('image')->nullable()->after('handphone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
