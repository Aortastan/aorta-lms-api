<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SessionTest;
use App\Models\StudentQuiz;
use App\Models\StudentPretestPosttest;
use App\Models\StudentTryout;
use App\Models\Question;
use Illuminate\Support\Facades\Validator;

class SubmitTestController extends Controller
{
    public function submitTest(Request $request, $session_uuid){
        $validate = [
            'duration_left' => 'required',
            'data_question' => 'required',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user_session = SessionTest::where(['uuid' => $session_uuid])->first();
        if($user_session == null){
            return response()->json([
                'message'=>'Session not found'
            ], 404);
        }

        $data_question = [];
        $points = 0;

        foreach ($request->data_questions as $index => $data) {
            $get_question = Question::where([
                'uuid' => $data->question_uuid,
            ])->with(['answers'])->first();

            if($get_question->different_point == 0){
                $different_point = 0;
            }

            $answers = [];
            $is_true = 0;
            foreach ($get_question->answers as $index1 => $answer) {
                $is_selected = 0;
                if(in_array($answer['uuid'], $data->answer_uuid)){
                    $is_selected = 1;
                }

                if($get_question->different_point == 0){
                    if($answer['is_correct'] == 1){
                        $is_true = 1;
                    }
                }else{
                    if($answer['is_correct'] == 1){
                        $points += $answer['point'];
                    }
                }

                $answers[]=[
                    'answer_uuid' => $answer['uuid'],
                    'is_correct' => $answer['is_correct'],
                    'is_selected' => $is_selected,
                ];
            }

            if($get_question->different_point == 0){
                if($is_true == 1){
                    $points += $get_question->point;
                }
            }

            $data_question[] = [
                "question_uuid" => $data->question_uuid,
                "answers" => $answers,
            ];
        }

        if($user_session->type_test == 'quiz'){
            StudentQuiz::create([
                'data_question' => json_encode($data_question),
                'user_uuid' => $user_session->user_uuid,
                'lesson_quiz_uuid' => $user_session->lesson_quiz_uuid,
                'score' => $points,
            ]);
        }elseif($user_session->type_test == 'pretest_posttest'){
            StudentPretestPosttest::create([
                'user_uuid' => $user_session->user_uuid,
                'data_question' => json_encode($data_question),
                'pretest_posttest_uuid' => $user_session->pretest_posttest_uuid,
                'score' => $points,
            ]);
        }elseif($user_session->type_test == 'tryout'){
            StudentTryout::create([
                'data_question' => json_encode($data_question),
                'user_uuid' => $user_session->user_uuid,
                'package_test_uuid' => $user_session->package_test_uuid,
                'score' => $points,
            ]);
        }

        SessionTest::where(['uuid' => $session_uuid])->delete();

        return response()->json([
            'message'=>'Test submitted',
            'score' => $points,
        ], 200);
    }
}
