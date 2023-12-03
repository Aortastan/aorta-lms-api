<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid');
            $table->string('course_uuid');
            $table->string('lesson_uuid');
            $table->string('lecture_uuid');
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
        Schema::dropIfExists('student_progress');
    }
}
