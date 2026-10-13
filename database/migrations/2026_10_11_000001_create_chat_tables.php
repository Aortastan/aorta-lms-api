<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatTables extends Migration
{
    /**
     * Web chat Aorta: satu sesi per pengunjung (guest via visitor key di browser,
     * atau user login), pesan user/bot/system disimpan untuk riwayat & kelola chat admin.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid')->nullable()->index();
            // Kode acak untuk link riwayat chat di pesan WA admin (tidak membuka uuid sesi)
            $table->string('share_code', 16)->nullable()->unique();
            // sha256 dari visitor key acak di localStorage browser (guest)
            $table->string('visitor_key_hash', 64)->nullable()->index();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('bot_state')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_user_message_at')->nullable();
            $table->timestamp('admin_read_at')->nullable();
            $table->timestamp('handoff_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('chat_session_uuid');
            $table->string('sender', 10); // user | bot | system
            $table->text('body')->nullable();
            $table->text('media')->nullable(); // {url, name, mimetype}
            $table->text('meta')->nullable();  // {handoff, not_understood}
            $table->timestamps();

            $table->index(['chat_session_uuid', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
}
