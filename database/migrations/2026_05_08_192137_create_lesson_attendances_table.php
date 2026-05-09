<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('user_uuid');
            $table->string('lesson_lecture_uuid');
            $table->timestamp('start_attendance')->nullable();
            $table->timestamp('end_attendance')->nullable();
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
        Schema::dropIfExists('lesson_attendances');
    }
}
