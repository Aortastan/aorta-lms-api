<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddParentLectureUuidToLessonLectures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lesson_lectures', function (Blueprint $table) {
            $table->string('parent_lecture_uuid')->nullable()->after('lesson_uuid');
            $table->integer('order')->default(0)->after('parent_lecture_uuid');
            $table->index('parent_lecture_uuid');
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
            $table->dropIndex(['parent_lecture_uuid']);
            $table->dropColumn(['parent_lecture_uuid', 'order']);
        });
    }
}
