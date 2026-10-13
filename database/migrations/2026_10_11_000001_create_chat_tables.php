<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatTables extends Migration
{
    public function up()
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid')->nullable()->unique();
            $table->string('share_code', 16)->nullable()->unique();
            $table->string('visitor_key_hash', 64)->nullable()->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('bot_state')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_user_message_at')->nullable();
            $table->timestamp('admin_read_at')->nullable();
            $table->timestamp('handoff_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('chat_session_uuid');
            $table->string('sender', 10);
            $table->text('body')->nullable();
            $table->text('media')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->index(['chat_session_uuid', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
}
