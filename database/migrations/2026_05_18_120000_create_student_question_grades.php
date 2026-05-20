<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentQuestionGrades extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_question_grades', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Polymorphic-like link ke attempt (student_tests | student_quizzes | student_tryouts)
            $table->string('student_quiz_uuid')->index();
            $table->string('student_quiz_type', 20)->default('test'); // 'test' | 'quiz' | 'tryout'

            $table->string('question_uuid')->index();
            $table->string('user_uuid')->index();

            $table->longText('answer_text')->nullable(); // Jawaban essay siswa
            $table->decimal('score_awarded', 8, 2)->nullable(); // null = belum di-grade
            $table->text('feedback')->nullable();

            $table->string('scored_by_uuid')->nullable(); // instructor UUID
            $table->timestamp('scored_at')->nullable();

            $table->string('status', 20)->default('pending'); // pending | scored

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
        Schema::dropIfExists('student_question_grades');
    }
}
