<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesForPerformanceOptimization extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Index pada tabel answers untuk mempercepat pencarian jawaban berdasarkan question_uuid
        Schema::table('answers', function (Blueprint $table) {
            if (Schema::hasColumn('answers', 'question_uuid')) {
                $table->index('question_uuid', 'answers_question_uuid_idx');
            }
        });

        // 2. Index pada student_tryouts untuk mempercepat count attempt dan filter per user & package_test
        Schema::table('student_tryouts', function (Blueprint $table) {
            if (Schema::hasColumns('student_tryouts', ['user_uuid', 'package_test_uuid'])) {
                $table->index(['user_uuid', 'package_test_uuid'], 'student_tryouts_user_package_test_idx');
            }
            if (Schema::hasColumn('student_tryouts', 'package_test_uuid')) {
                $table->index('package_test_uuid', 'student_tryouts_package_test_idx');
            }
        });

        // 3. Index pada student_quizzes dan student_pretest_posttests untuk riwayat attempt
        Schema::table('student_quizzes', function (Blueprint $table) {
            if (Schema::hasColumns('student_quizzes', ['user_uuid', 'lesson_quiz_uuid'])) {
                $table->index(['user_uuid', 'lesson_quiz_uuid'], 'student_quizzes_user_lesson_idx');
            }
        });

        Schema::table('student_pretest_posttests', function (Blueprint $table) {
            if (Schema::hasColumns('student_pretest_posttests', ['user_uuid', 'pretest_posttest_uuid'])) {
                $table->index(['user_uuid', 'pretest_posttest_uuid'], 'student_pretest_posttests_user_pretest_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex('answers_question_uuid_idx');
        });

        Schema::table('student_tryouts', function (Blueprint $table) {
            $table->dropIndex('student_tryouts_user_package_test_idx');
            $table->dropIndex('student_tryouts_package_test_idx');
        });

        Schema::table('student_quizzes', function (Blueprint $table) {
            $table->dropIndex('student_quizzes_user_lesson_idx');
        });

        Schema::table('student_pretest_posttests', function (Blueprint $table) {
            $table->dropIndex('student_pretest_posttests_user_pretest_idx');
        });
    }
}
