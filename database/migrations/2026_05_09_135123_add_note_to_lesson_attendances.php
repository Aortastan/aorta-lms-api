<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoteToLessonAttendances extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {

            $table->text('note')->nullable();
            $table->enum('note_status', ['rejected', 'approved'])->nullable();
            $table->string('note_approved_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lesson_attendances', function (Blueprint $table) {
            //
        });
    }
}
