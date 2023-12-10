<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PretestPosttest;
use App\Models\StudentPretestPosttest;
use App\Models\Course;
use App\Models\PackageCourse;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\Test;
use App\Models\Question;
use App\Models\Answer;
use App\Models\SessionTest;
use Tymon\JWTAuth\Facades\JWTAuth;

class PretestPosttestController extends Controller
{
    public function index($test_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getTest = PretestPosttest::
                select(
                    'uuid',
                    'test_uuid',
                    'course_uuid',
                    'duration',
                    'max_attempt'
                )
                ->where(['uuid' => $test_uuid])
                ->first();

            if(!$getTest){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }

            $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getTest->course_uuid);

            if($checkCourseIsPurchasedOrMembership != null){
                return $checkCourseIsPurchasedOrMembership;
            }

            $pretest_posttests = StudentPretestPosttest::
            select('uuid', 'score')
            ->where([
                'user_uuid' => $user->uuid,
                'pretest_posttest_uuid' => $getTest->uuid,
            ])->get();

            $getTest['student_pretest_posttest'] = $pretest_posttests;

            return response()->json([
                'message' => 'Success get data',
                'pretest_posttest' => $getTest,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show($student_test_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $pretest_posttests = StudentPretestPosttest::
            select('uuid', 'score', 'pretest_posttest_uuid', 'data_question')
            ->where([
                'user_uuid' => $user->uuid,
                'uuid' => $student_test_uuid,
            ])->first();


            if($pretest_posttests == null){
                return response()->json([
                    'message' => 'Test not found'
                ], 404);
            }

            $getTest = PretestPosttest::
                select(
                    'uuid',
                    'test_uuid',
                    'course_uuid',
                    'duration',
                    'max_attempt'
                )
                ->where(['uuid' => $pretest_posttests->pretest_posttest_uuid])
                ->first();

            if(!$getTest){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }

            $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getTest->course_uuid);

            if($checkCourseIsPurchasedOrMembership != null){
                return $checkCourseIsPurchasedOrMembership;
            }



            $data_question = json_decode($pretest_posttests->data_question);

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

                $answers[] = [
                    'is_correct' => $answer->is_correct,
                    'is_selected' => $answer->is_selected,
                    'answer' => $get_answer->answer,
                    'image' => $get_answer->image,
                ];
            }

            $questions[] = [
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
            'message' => 'Success get data',
            'score' => $pretest_posttests->score,
            'questions' => $questions
        ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function checkCourseIsPurchasedOrMembership($user, $course_uuid){
        // cek apakah course uuid tersebut ada
        $course = Course::where([
            'uuid' => $course_uuid,
        ])->first();

        // cek package mana aja yang menyimpan course tersebut
        $check_package_courses = PackageCourse::where([
            'course_uuid' => $course->uuid,
        ])->get();

        $package_uuids = [];
        foreach ($check_package_courses as $index => $package) {
            $package_uuids[] = $package->package_uuid;
        }

        if(count($package_uuids) <= 0){
            return response()->json([
                'message' => "Package course not found",
            ]);
        }

        // cek apakah user pernah membeli lifetime package tersebut
        $check_purchased_package = PurchasedPackage::where([
            "user_uuid" => $user->uuid,
        ])->whereIn("package_uuid", $package_uuids)->first();

        // jika ternyata tidak ada, maka sekarang cek di membership
        if($check_purchased_package == null){
            $check_membership_package = MembershipHistory::where([
                "user_uuid" => $user->uuid,
            ])
            ->whereDate('expired_date', '>', now())
            ->whereIn("package_uuid", $package_uuids)->first();

            if($check_membership_package == null){
                return response()->json([
                    'message' => 'You can\'t access this course',
                ]);
            }
        }

        return null;
    }

    public function takeTest($test_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getTest = PretestPosttest::
            select(
                'uuid',
                'test_uuid',
                'course_uuid',
                'duration',
                'max_attempt'
            )
            ->where(['uuid' => $test_uuid])
            ->first();

        if(!$getTest){
            return response()->json([
                'message' => "Test not found",
            ], 404);
        }

        // cek apakah course tersebut sudah pernah dibeli atau belum
        $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getTest->course_uuid);
        if($checkCourseIsPurchasedOrMembership != null){
            return $checkCourseIsPurchasedOrMembership;
        }

        // cek apakah test sudah melewati max attempt
        $checkTestMaxAttempt = $this->checkTestMaxAttempt($user, $getTest);
        if($checkTestMaxAttempt != null){
            return $checkTestMaxAttempt;
        }

        // cek session
        $sessionTest = $this->checkTestSession($user, $getTest);

        $questions = [];

        $data_questions = json_decode($sessionTest->data_question);


        foreach ($data_questions as $index => $data) {
            $get_question = Question::where([
                'uuid' => $data->question_uuid,
            ])->with(['answers'])->first();

            $answers = [];

            foreach ($get_question->answers as $index1 => $answer) {
                $is_selected = 0;
                if(in_array($answer['uuid'], $data->answer_uuid)){
                    $is_selected = 1;
                }

                $answers[]=[
                    'answer_uuid' => $answer['uuid'],
                    'answer' => $answer['answer'],
                    'image' => $answer['image'],
                    'is_selected' => $is_selected,
                ];
            }

            $questions[] = [
                'question_uuid' => $data->question_uuid,
                'status' => $data->status,
                'title' => $get_question->title,
                'question_type' => $get_question->question_type,
                'question' => $get_question->question,
                'file_path' => $get_question->file_path,
                'url_path' => $get_question->url_path,
                'type' => $get_question->type,
                'hint' => $get_question->hint,
                'answers' => $answers,
            ];
        }

        $test = [
            'session_uuid' => $sessionTest->uuid,
            'duration_left' => $sessionTest->duration_left,
            'questions' => $questions,
        ];

        return response()->json([
            'message' => "Success get data",
            'question' => $test,
        ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function checkTestMaxAttempt($user, $test){
        $studentTest = StudentPretestPosttest::where([
            'user_uuid' => $user->uuid,
            'pretest_posttest_uuid' => $test->uuid,
        ])->count();

        if($studentTest >= $test->max_attempt){
            return response()->json([
                'success' => false,
                'message' => "You have passed the maximum number of attempts",
            ]);
        }
        return null;
    }

    public function checkTestSession($user, $test){
        $sessionTest = SessionTest::where([
            'user_uuid' => $user->uuid,
            'pretest_posttest_uuid' => $test->uuid,
            'type_test' => 'pretest_posttest',
        ])->first();

        if($sessionTest == null){
            $sessionTest = $this->createTestSession($user, $test);
        }

        return $sessionTest;
    }
    public function createTestSession($user, $test){
        try{
            $data_question = [];
        $get_test = Test::where([
            'uuid' => $test->test_uuid
        ])->with(['questions'])->first();

        foreach ($get_test->questions as $index => $data) {
            $data_question[] = [
                'question_uuid' => $data->question_uuid,
                'answer_uuid' => [],
                'status' => '',
            ];
        }

        $sessionTest = SessionTest::create([
            'user_uuid' => $user->uuid,
            'duration_left' => $test->duration,
            'pretest_posttest_uuid' => $test->uuid,
            'type_test' => 'pretest_posttest',
            'test_uuid' => $test->test_uuid,
            'data_question' => json_encode($data_question),
        ]);

        return $sessionTest;
        }catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
