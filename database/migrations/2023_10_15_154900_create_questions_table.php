<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('subject_uuid');
            $table->string('question_type')->comment('multiple, most point');
            $table->string('question');
            $table->string('file_path')->nullable();
            $table->string('url_path')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_duration')->nullable();
            $table->string('file_duration_seconds')->nullable();
            $table->string('type')->comment('video, youtube, text, image, pdf, slide document, audio');
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
        Schema::dropIfExists('questions');
    }
}
