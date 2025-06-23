<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonQuiz;
use App\Models\StudentQuiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\CourseLesson;

class QuizController extends Controller
{
    public function preview(Request $request, $uuid){
        $test = LessonQuiz::
            select(
                'uuid',
                'test_uuid',
                'title',
                'description',
                'duration',
                'max_attempt',
                'status'
            )
            ->with(['test', 'test.questions', 'test.questions.question', 'test.questions.question.answers'])
            ->where(['uuid' => $uuid])
            ->first();

        if(!$test){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $getQuestion = [];
        foreach ($test->test->questions as $key => $data) {
            $answers = [];
            foreach ($data['question']['answers'] as $index => $answer) {
                $answers[] = [
                    'answer_uuid' => $answer['uuid'],
                    'answer' => $answer['answer'],
                    'image' => $answer['image'],
                    'is_correct' => $answer['is_correct'],
                    'answer_correct_explanation' => $answer['answer_correct_explanation'],
                    'point' => $answer['point'],
                    'is_selected' => 0,
                ];
            }
            $getQuestion[] = [
                'question_uuid' => $data['question']['uuid'],
                'status' => $data['question']['status'],
                'title' => $data['question']['title'],
                'question_type' => $data['question']['question_type'],
                'question' => $data['question']['question'],
                'file_path' => $data['question']['file_path'],
                'url_path' => $data['question']['url_path'],
                'type' => $data['question']['type'],
                'hint' => $data['question']['hint'],
                'answers' => $answers,
            ];
        }

        $test = [
            'uuid' => $test['uuid'],
            'questions' => $getQuestion,
        ];

        return response()->json([
            'message' => "Success get data",
            'question' => $test,
        ], 200);
    }

    public function reviewTryout($student_quiz_uuid, $user_uuid){
        $student_quiz = StudentQuiz::
        select('uuid', 'data_question', 'score', 'lesson_quiz_uuid')
        ->where([
            'user_uuid' => $user_uuid,
            'uuid' => $student_quiz_uuid,
        ])->first();

        if(!$student_quiz){
            return response()->json([
                'message' => "Student Quiz not found",
            ], 404);
        }

        $getQuiz = LessonQuiz::
            select('uuid', 'title', 'lesson_uuid', 'description', 'duration', 'max_attempt')
            ->where(['uuid' => $student_quiz->lesson_quiz_uuid])
            ->first();

        if(!$getQuiz){
            return response()->json([
                'message' => "Quiz not found",
            ], 404);
        }

        $getLesson = CourseLesson::
            where(['uuid' => $getQuiz->lesson_uuid])
            ->first();

        $data_question = json_decode($student_quiz->data_question);

        $questions = [];
        foreach ($data_question as $index => $data) {
            $get_question = Question::where([
                'uuid' => $data->question_uuid,
            ])->first();

            $answers = [];
            foreach ($data->answers as $index => $answer) {
                $get_answer = Answer::where([
                    'uuid' => $answer->answer_uuid,
                ])->first();

                if($answer->is_correct) {
                    $answers[] = [
                        'answer_uuid' => $answer->answer_uuid,
                        'is_correct' => $answer->is_correct,
                        'correct_answer_explanation' => $get_answer->correct_answer_explanation,
                        'is_selected' => $answer->is_selected,
                        'answer' => $get_answer->answer,
                        'image' => $get_answer->image,
                    ];
                } else {
                    $answers[] = [
                        'answer_uuid' => $answer->answer_uuid,
                        'is_correct' => $answer->is_correct,
                        'is_selected' => $answer->is_selected,
                        'answer' => $get_answer->answer,
                        'image' => $get_answer->image,
                    ];
                }
            }

            $questions[] = [
                'question_uuid' => $get_question->uuid,
                'question_type' => $get_question->question_type,
                'question' => $get_question->question,
                'file_path' => $get_question->file_path,
                'url_path' => $get_question->url_path,
                'file_size' => $get_question->file_size,
                'file_duration' => $get_question->file_duration,
                'type' => $get_question->type,
                'hint' => $get_question->hint,
                'answers' => $answers,
            ];
        }

        return response()->json([
            'message' => 'Sukses mengambil data',
            'score' => $student_quiz->score,
            'questions' => $questions
        ], 200);
    }
}
