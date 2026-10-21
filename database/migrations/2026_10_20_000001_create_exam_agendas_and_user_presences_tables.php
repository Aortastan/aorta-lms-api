<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExamAgendasAndUserPresencesTables extends Migration
{
    public function up()
    {
        Schema::create('exam_agendas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('stage_label', 50);
            $table->string('title', 150);
            $table->string('description')->nullable();
            $table->dateTime('start_at')->index();
            $table->dateTime('end_at')->nullable();
            $table->string('visibility', 10)->default('all');
            $table->text('package_uuids')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('user_presences', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid')->unique();
            $table->string('activity', 20)->default('browsing');
            $table->uuid('test_ref_uuid')->nullable();
            $table->uuid('package_uuid')->nullable();
            $table->string('path', 191)->nullable();
            $table->timestamp('activity_started_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_presences');
        Schema::dropIfExists('exam_agendas');
    }
}
