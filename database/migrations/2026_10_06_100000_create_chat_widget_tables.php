<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Live Chat Widget (6 Oktober 2026): widget chat yang bisa dipasang di
 * website mana pun (public/widget.js), dijawab AI cabang (App\Models\
 * WaAiBot) dan bisa diambil alih CS dari "Live Chat Inbox".
 *
 * Semua tabel fitur ini berawalan chat_widget*, dan down() menghapus
 * tabel + menu yang dibuat di sini -- jadi fitur bisa dibuang bersih
 * dengan `php artisan migrate:rollback` (lihat docs di ChatWidget model).
 */
return new class extends Migration
{
    private const MENUS = [
        ['route_name' => 'chat.widgets.index', 'name' => 'Live Chat Widget', 'icon' => 'ri-chat-smile-2-line', 'sort_order' => 78],
        ['route_name' => 'chat.widget-inbox.index', 'name' => 'Live Chat Inbox', 'icon' => 'ri-chat-3-line', 'sort_order' => 79],
    ];

    public function up(): void
    {
        Schema::create('chat_widgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('branch_office_id')->nullable()->constrained('branch_offices')->nullOnDelete();
            $table->string('name');
            $table->string('public_key', 64)->unique();
            $table->json('allowed_domains');
            $table->json('settings')->nullable();
            $table->boolean('ai_enabled')->default(true);
            $table->string('status', 16)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_domain')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'branch_office_id'], 'cw_widget_company_branch_idx');
        });

        Schema::create('chat_widget_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_widget_id')->constrained('chat_widgets')->cascadeOnDelete();
            $table->string('visitor_token_hash', 64)->unique();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_phone', 30)->nullable();
            $table->string('page_url', 500)->nullable();
            // ai = dijawab AI, waiting = minta CS, agent = ditangani CS, closed = selesai
            $table->string('status', 16)->default('ai');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // Nama index dibuat pendek: nama otomatis Laravel melebihi batas 64 karakter MySQL.
            $table->index(['chat_widget_id', 'status', 'last_message_at'], 'cw_conv_widget_status_idx');
        });

        Schema::create('chat_widget_messages', function (Blueprint $table) {
            $table->id(); // auto-increment = urutan pesan & penanda polling (after_id)
            $table->foreignUuid('chat_widget_conversation_id')->constrained('chat_widget_conversations')->cascadeOnDelete();
            $table->string('sender', 16); // visitor | ai | agent | system
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_widget_conversation_id', 'id'], 'cw_msg_conv_idx');
        });

        $this->seedMenus();
    }

    public function down(): void
    {
        DB::table('application_menus')->whereIn('route_name', array_column(self::MENUS, 'route_name'))->delete();

        Schema::dropIfExists('chat_widget_messages');
        Schema::dropIfExists('chat_widget_conversations');
        Schema::dropIfExists('chat_widgets');
    }

    /** Daftarkan menu supaya bisa diberikan ke role staff (lihat CompanyContext::canAccessRoute()). */
    private function seedMenus(): void
    {
        $categoryId = DB::table('category_applications')
            ->whereRaw('LOWER(name) LIKE ?', ['%chat%'])
            ->orderBy('created_at')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        foreach (self::MENUS as $menu) {
            if (DB::table('application_menus')->where('route_name', $menu['route_name'])->exists()) {
                continue;
            }

            DB::table('application_menus')->insert($menu + [
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'category_application_id' => $categoryId,
                'parent_id' => null,
                'description' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
