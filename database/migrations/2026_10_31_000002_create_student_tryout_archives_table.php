<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentTryoutArchivesTable extends Migration
{
    public function up()
    {
        Schema::create('student_tryout_archives', function (Blueprint $table) {
            $table->id();
            $table->string('student_tryout_uuid');
            $table->string('package_test_uuid')->index();
            $table->string('user_uuid')->index();
            $table->string('package_uuid')->nullable();
            $table->integer('attempt')->nullable();
            $table->integer('score')->nullable();
            $table->mediumText('payload');
            $table->mediumText('grades_payload')->nullable();
            $table->string('reason', 50);
            $table->string('reset_by')->nullable();
            $table->timestamp('reset_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('student_tryout_archives');
    }
}
