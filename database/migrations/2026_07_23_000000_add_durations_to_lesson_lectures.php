<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDurationsToLessonLectures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lesson_lectures', function (Blueprint $table) {
            $table->integer('attendance_start_duration')->nullable()->after('attendance_started_at');
            $table->integer('attendance_end_duration')->nullable()->after('attendance_ended_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lesson_lectures', function (Blueprint $table) {
            $table->dropColumn(['attendance_start_duration', 'attendance_end_duration']);
        });
    }
}
