<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentTryout;
use App\Models\PackageTest;
use App\Models\MembershipHistory;
use App\Models\PurchasedPackage;
use App\Models\TryoutSegmentTest;
use App\Models\TryoutSegment;
use App\Models\Tryout;
use App\Models\SessionTest;
use App\Models\Test;
use App\Models\Question;
use App\Models\Answer;
use Tymon\JWTAuth\Facades\JWTAuth;
use Auth;
use DB;

class TryoutController extends Controller
{
    public function index($tryout_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getTest = TryoutSegmentTest::
                select(
                    'uuid',
                    'test_uuid',
                    'attempt',
                    'duration',
                )
                ->where(['uuid' => $tryout_uuid])
                ->first();
            if(!$getTest){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }
            $checkTestIsPurchasedOrMembership = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid);
            if($checkTestIsPurchasedOrMembership != null){
                return $checkTestIsPurchasedOrMembership;
            }
            $pretest_posttests = StudentTryout::
            select('uuid', 'score')
            ->where([
                'user_uuid' => $user->uuid,
                'package_test_uuid' => $getTest->uuid,
            ])->get();
            $getTest['student_attempts'] = $pretest_posttests;

            return response()->json([
                'message' => 'Success Get All Tryout Data',
                'tryout' => $getTest,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show($tryout_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $tryout = StudentTryout::
            select('uuid', 'score', 'package_test_uuid', 'data_question')
            ->where([
                'user_uuid' => $user->uuid,
                'uuid' => $tryout_uuid,
            ])->first();

            if($tryout == null){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }

            $getTest = TryoutSegmentTest::
                select(
                    'uuid',
                    'test_uuid',
                    'attempt',
                    'duration',
                )
                ->where(['uuid' => $tryout->package_test_uuid])
                ->first();

            if(!$getTest){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }

            $checkTestIsPurchasedOrMembership = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid);

            if($checkTestIsPurchasedOrMembership != null){
                return $checkTestIsPurchasedOrMembership;
            }

            $data_question = json_decode($tryout->data_question);

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
                'message' => 'Success get data',
                'score' => $tryout->score,
                'questions' => $questions
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function checkTestIsPurchasedOrMembership($user, $tryout_segment_test){
        // cek
        $check_tryout_segment_test = TryoutSegmentTest::where([
            'uuid' => $tryout_segment_test,
        ])->first();

        if($check_tryout_segment_test == null){
            return response()->json([
                'message' => 'Tryout segment not found',
            ]);
        }

        // cek
        $check_tryout_segment = TryoutSegment::where([
            'uuid' => $check_tryout_segment_test->tryout_segment_uuid,
        ])->first();

        // cek
        $check_tryout = Tryout::where([
            'uuid' => $check_tryout_segment->tryout_uuid,
        ])->first();



        // cek package mana aja yang menyimpan course tersebut
        $check_package_tests = PackageTest::where([
            'test_uuid' => $check_tryout->uuid,
        ])->get();

        $package_uuids = [];
        foreach ($check_package_tests as $index => $package) {
            $package_uuids[] = $package->package_uuid;
        }

        if(count($package_uuids) <= 0){
            return response()->json([
                'message' => "Package test not found",
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

    public function takeTest($tryout_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getTest = TryoutSegmentTest::
            select(
                'uuid',
                'test_uuid',
                'attempt',
                'duration',
                'max_point'
            )
            ->where(['uuid' => $tryout_uuid])
            ->first();

        if(!$getTest){
            return response()->json([
                'message' => "Test not found",
            ], 404);
        }

        // cek apakah course tersebut sudah pernah dibeli atau belum
        $checkTestIsPurchasedOrMembership = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid);
        if($checkTestIsPurchasedOrMembership != null){
            return $checkTestIsPurchasedOrMembership;
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
        $studentTest = StudentTryout::where([
            'user_uuid' => $user->uuid,
            'package_test_uuid' => $test->uuid,
        ])->count();

        if($studentTest >= $test->attempt){
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
            'package_test_uuid' => $test->uuid,
            'type_test' => 'tryout',
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
            'package_test_uuid' => $test->uuid,
            'type_test' => 'tryout',
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

    public function getUserTryoutAnalytic($tryout_uuid)
    {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if($tryout == null) {
            return response()->json([
                'message' => "Data not found",
            ], 404);
        }

        $list_score_per_segment = [];
        $segment_results=[];
        foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
            $list_score=[];
            $formattedResult=[];
            $countSegment = 0;
            foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                $countSegment += 1;
                $packageTestUuid = $tryout_segment_test['uuid'];
                $maxPoint = $tryout_segment_test['max_point'] ?? 0;

                // Fetch corresponding records in student_tryouts
                $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
                    ->where('student_tryouts.user_uuid', auth()->user()->uuid)
                    ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
                    ->orderBy('student_tryouts.created_at')
                    ->get();
                // Process each attempt for the test
                $attemptsResult = [];
                $first_score = 0;

                foreach ($attemptsData as $attemptData) {
                    $first_score = $attemptsData[0]['score'];
                    $percentage = $attemptData->score ? ($attemptData->score / $maxPoint) * 100 : 0;
                    $attemptsResult[] = [
                        'attempt_uuid' => $attemptData->uuid,
                        'package_test_uuid' => $attemptData->package_test_uuid,
                        'score' => $attemptData->score ?: 0,
                        'percentage' => $percentage,
                    ];
                }
                $list_score[] = $first_score;

                // Add the test data with attempts to the result
                $formattedResult[] = [
                    'tryout_segment_test_uuid' => $tryout_segment_test['uuid'],
                    'test_name' => $tryout_segment_test['test']['name'],
                    'max_point' => $maxPoint,
                    'attempts' => $attemptsResult,
                ];
            }
            // Menghitung total nilai
            $total = array_sum($list_score);

            if($countSegment <= 0){
                $countSegment = 1;
            }

            // Menghitung rata-rata
            $average = $total / $countSegment;

            $list_score_per_segment[] = $average;
            $segment_results[] = [
                "tryout_segment_uuid" => $tryout_segment['uuid'],
                'segment_name' => $tryout_segment['title'],
                'segment_score' => $average,
                'segment_result' => $formattedResult,
            ];
        }

        $count = count($list_score_per_segment);

        // Menghitung total nilai
        $total = array_sum($list_score_per_segment);

        // Menghitung rata-rata
        $average = $total / $count;

        $tryout_result = [
            "tryout_uuid" => $tryout_uuid,
            'tryout_name' => $tryout['title'],
            'score' => intval($average),
            'tryout_result' => $segment_results,
        ];

        return response()->json([
            'message' => 'Success get user tryout analytics',
            'status' => true,
            'data' => $tryout_result,
        ], 200);
    }

    public function getLeaderboard($tryout_uuid) {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if($tryout == null) {
            return response()->json([
                'message' => "Data not found",
            ], 404);
        }

        $tryout_segment_test_uuids = [];
        foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
            $list_score=[];
            $formattedResult=[];
            $countSegment = 0;
            foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                $tryout_segment_test_uuids[] = $tryout_segment_test['uuid'];
            }
        }

        $tryout_result=[];
        $earliestAttempts = StudentTryout::select('user_uuid')
        ->whereIn('package_test_uuid', $tryout_segment_test_uuids)
        ->with(['user'])
        ->groupBy('user_uuid');
        $earliestAttempts = $earliestAttempts->groupBy('package_test_uuid')->get();
        $user_uuids= [];
        $users= [];
        foreach ($earliestAttempts as $key => $value) {
            if (!in_array($value->user_uuid, $user_uuids)) {
                $user_uuids[] = $value->user_uuid;
                $users[] = [
                    "user_uuid" => $value->user_uuid,
                    "user_name" => $value->user->name,
                ];
            }

        }




        foreach ($users as $index => $user_attempt) {
            $list_score_per_segment = [];
            $segment_results=[];
            foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
                $list_score=[];
                $formattedResult=[];
                $countSegment = 0;
                foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                    $countSegment += 1;
                    $packageTestUuid = $tryout_segment_test['uuid'];
                    $maxPoint = $tryout_segment_test['max_point'] ?? 0;

                    // Fetch corresponding records in student_tryouts
                    $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
                        ->where('student_tryouts.user_uuid', $user_attempt['user_uuid'])
                        ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
                        ->orderBy('student_tryouts.created_at')
                        ->get();
                    // Process each attempt for the test
                    $attemptsResult = [];
                    $first_score = 0;

                    foreach ($attemptsData as $attemptData) {
                        $first_score = $attemptsData[0]['score'];
                        $percentage = $attemptData->score ? ($attemptData->score / $maxPoint) * 100 : 0;
                        $attemptsResult[] = [
                            'attempt_uuid' => $attemptData->uuid,
                            'package_test_uuid' => $attemptData->package_test_uuid,
                            'score' => $attemptData->score ?: 0,
                            'percentage' => $percentage,
                        ];
                    }
                    $list_score[] = $first_score;

                    // Add the test data with attempts to the result
                    $formattedResult[] = [
                        'tryout_segment_test_uuid' => $tryout_segment_test['uuid'],
                        'test_name' => $tryout_segment_test['test']['name'],
                        'max_point' => $maxPoint,
                        'attempts' => $attemptsResult,
                    ];
                }
                // Menghitung total nilai
                $total = array_sum($list_score);

                if($countSegment <= 0){
                    $countSegment = 1;
                }

                // Menghitung rata-rata
                $average = $total / $countSegment;

                $list_score_per_segment[] = $average;
                $segment_results[] = [
                    "tryout_segment_uuid" => $tryout_segment['uuid'],
                    'segment_name' => $tryout_segment['title'],
                    'segment_score' => $average,
                    'segment_result' => $formattedResult,
                ];
            }

            $count = count($list_score_per_segment);

            // Menghitung total nilai
            $total = array_sum($list_score_per_segment);

            // Menghitung rata-rata
            $average = $total / $count;

            $tryout_result[] = [
                "user_uuid" => $user_attempt['user_uuid'],
                "name" => $user_attempt['user_name'],
                "tryout_uuid" => $tryout_uuid,
                'tryout_name' => $tryout['title'],
                'score' => intval($average),
            ];
        }

        usort($tryout_result, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Menambahkan key ranking
        $ranking = 1;
        foreach ($tryout_result as &$item) {
            $item['ranking'] = $ranking;
            $ranking++;
        }

        $data['currentUser'] = [
            'uuid' =>null,
            'ranking' => null,
        ];
        foreach ($tryout_result as $index => $result) {
            if($result['user_uuid'] == auth()->user()->uuid){
                $data['currentUser'] = [
                    'uuid' => auth()->user()->uuid,
                    'ranking' => $result['ranking'],
                ];
            }
        }
        $data['allLeaderboard'] = $tryout_result;

        return response()->json([
            'message' => 'Success get Tryout Leaderboard',
            'status' => true,
            'data' => $data
        ], 200);
    }

}
