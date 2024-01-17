<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentTryoutsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_tryouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->text('data_question');
            $table->string('user_uuid');
            $table->string('package_test_uuid');
            $table->integer('attempt');
            $table->integer('score');
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
        Schema::dropIfExists('student_tryouts');
    }
}
