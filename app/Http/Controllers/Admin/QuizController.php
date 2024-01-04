<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonQuiz;

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
                'status',
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
}
