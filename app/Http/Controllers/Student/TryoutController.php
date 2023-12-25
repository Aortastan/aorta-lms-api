<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentTryout;
use App\Models\PackageTest;
use App\Models\MembershipHistory;
use App\Models\PurchasedPackage;
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
            $getTest = PackageTest::
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

            $getTest = PackageTest::
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

    public function checkTestIsPurchasedOrMembership($user, $tryout_uuid){
        // cek package
        $check_package_test = PackageTest::where([
            'uuid' => $tryout_uuid,
        ])->first();

        // cek apakah user pernah membeli lifetime package tersebut
        $check_purchased_package = PurchasedPackage::where([
            "user_uuid" => $user->uuid,
            "package_uuid" => $check_package_test->package_uuid,
        ])->first();

        // jika ternyata tidak ada, maka sekarang cek di membership
        if($check_purchased_package == null){
            $check_membership_package = MembershipHistory::where([
                "user_uuid" => $user->uuid,
                "package_uuid" => $check_package_test->package_uuid,
            ])
            ->whereDate('expired_date', '>', now())
            ->first();

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
            $getTest = PackageTest::
            select(
                'uuid',
                'test_uuid',
                'attempt',

                'duration'
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

    public function getUserTryoutAnalytic($package_uuid)
    {
    // Get all tests related to the package_uuid
    $testsData = PackageTest::select(
        'tests.uuid as test_uuid',
        'tests.title as test_name',
        'package_tests.max_point',
        'package_tests.uuid'
    )
        ->leftJoin('tests', 'tests.uuid', '=', 'package_tests.test_uuid')
        ->where('package_tests.package_uuid', $package_uuid)
        ->get();

    $formattedResult = [];

    // Iterate through each test
    foreach ($testsData as $testData) {
        $packageTestUuid = $testData->uuid;
        $maxPoint = $testData->max_point ?? 0;

        // Fetch corresponding records in student_tryouts
        $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
            ->where('student_tryouts.user_uuid', auth()->user()->uuid)
            ->where('student_tryouts.package_test_uuid', $packageTestUuid)
            ->orderBy('student_tryouts.created_at')
            ->get();
        // Process each attempt for the test
        $attemptsResult = [];
        foreach ($attemptsData as $attemptData) {
            $percentage = $attemptData->score ? ($attemptData->score / $maxPoint) * 100 : 0;
            $attemptsResult[] = [
                'uuid' => $attemptData->tryout_uuid,
                'package_test_uuid' => $attemptData->package_test_uuid,
                'score' => $attemptData->score ?: 0,
                'percentage' => $percentage,
            ];
        }

        // Add the test data with attempts to the result
        $formattedResult[] = [
            'test_name' => $testData->test_name,
            'max_point' => $maxPoint,
            'attempts' => $attemptsResult,
        ];
    }

    return response()->json([
        'message' => 'Success get user tryout analytics',
        'status' => true,
        'data' => $formattedResult,
    ], 200);
    }

    public function getLeaderboard($package_uuid) {
        $currentUserUuid = Auth::user()->uuid;
        // Subquery to get the earliest attempt for each user and package
        $earliestAttempts = StudentTryout::select('user_uuid', 'package_uuid', DB::raw('MIN(created_at) as earliest_attempt'))
            ->where('package_uuid', $package_uuid)
            ->groupBy('user_uuid', 'package_uuid');
        // Main query to get all users' ranking based on their earliest attempt for each package
        $allLeaderboard = StudentTryout::select('users.name', 'student_tryouts.user_uuid', 'student_tryouts.package_uuid', DB::raw('SUM(student_tryouts.score) as score'))
            ->join('users', 'users.uuid', 'student_tryouts.user_uuid')
            ->join('packages', 'packages.uuid', 'student_tryouts.package_uuid')
            ->leftJoinSub($earliestAttempts, 'earliest_attempts', function ($join) {
                $join->on('earliest_attempts.user_uuid', '=', 'student_tryouts.user_uuid');
                $join->on('earliest_attempts.package_uuid', '=', 'student_tryouts.package_uuid');
            })
            ->where('earliest_attempts.package_uuid', $package_uuid)
            ->groupBy('users.name', 'student_tryouts.user_uuid', 'student_tryouts.package_uuid')
            ->orderBy('score', 'desc')
            ->get();

        // Calculate ranking manually
        $ranking = 1;
        $previousScore = null;

        foreach ($allLeaderboard as $item) {
            if ($item->score !== $previousScore) {
                $item->ranking = $ranking;
                $ranking++;
            } else {
                $item->ranking = $ranking - 1;
            }

            $previousScore = $item->score;
        }

        // Find the current user's ranking
        $currentUserRanking = $allLeaderboard
            ->where('user_uuid', $currentUserUuid)
            ->first();

        $data['currentUser'] = [
            'uuid' => $currentUserUuid,
            'ranking' => $currentUserRanking ? $currentUserRanking->ranking : null,
        ];

        $data['allLeaderboard'] = $allLeaderboard;

        return response()->json([
            'message' => 'Success get Tryout Leaderboard',
            'status' => true,
            'data' => $data
        ], 200);
    }

}
