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
            $table->string('subject_uuid');
            $table->string('author_uuid');
            $table->string('title');
            $table->string('question_type')->comment('multi choice, most point, single choice, fill in blank, true false');
            $table->string('question');
            $table->string('file_path')->nullable();
            $table->string('url_path')->nullable();
            $table->string('file_size')->nullable();
            $table->string('file_duration')->nullable();
            $table->string('type')->comment('video, youtube, text, image, pdf, slide document, audio');
            $table->boolean('different_point');
            $table->integer('point')->nullable();
            $table->string('hint')->nullable();
            $table->string('status')->comment('Published, Waiting for review, Draft');
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
