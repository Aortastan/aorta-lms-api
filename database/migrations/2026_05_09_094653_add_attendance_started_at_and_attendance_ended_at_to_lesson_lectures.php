<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttendanceStartedAtAndAttendanceEndedAtToLessonLectures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lesson_lectures', function (Blueprint $table) {
            //
            $table->timestamp('attendance_started_at')->nullable()->after('is_attendance_enabled');
            $table->timestamp('attendance_ended_at')->nullable()->after('attendance_started_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('course_lesson', function (Blueprint $table) {
            //
        });
    }
}
