<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('session_tests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid');
            $table->string('duration_left');
            $table->string('lesson_quiz_uuid')->nullable();
            $table->string('pretest_posttest_uuid')->nullable();
            $table->string('package_test_uuid')->nullable();
            $table->string('type_test')->comment('tryout, quiz, pretest posttest');
            $table->string('test_uuid');
            $table->text('data_question');
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
        Schema::dropIfExists('session_tests');
    }
}
