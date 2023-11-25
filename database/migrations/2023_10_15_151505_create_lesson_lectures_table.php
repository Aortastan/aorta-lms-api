<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonLecturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_lectures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('lesson_uuid');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('url_path')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_duration')->nullable();
            $table->string('type')->comment('video, youtube, video, text, image, pdf, slide document, audio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lesson_lectures');
    }
}
