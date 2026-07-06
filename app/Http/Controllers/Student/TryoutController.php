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
use App\Models\StudentQuestionGrade;
use Tymon\JWTAuth\Facades\JWTAuth;
use Auth;
use DB;

class TryoutController extends Controller
{
    public function index($tryout_uuid)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $getTest = TryoutSegmentTest::select(
                'uuid',
                'test_uuid',
                'attempt',
                'duration'
            )
                ->where(['uuid' => $tryout_uuid])
                ->first();
            if (!$getTest) {
                return response()->json([
                    'message' => "Tes tidak ditemukan",
                ], 404);
            }
            $checkTestIsPurchasedOrMembership = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid);
            if ($checkTestIsPurchasedOrMembership != null) {
                return $checkTestIsPurchasedOrMembership;
            }
            $pretest_posttests = StudentTryout::select('uuid', 'score')
                ->where([
                    'user_uuid' => $user->uuid,
                    'package_test_uuid' => $getTest->uuid,
                ])->get();
            $getTest['student_attempts'] = $pretest_posttests;

            return response()->json([
                'message' => 'Sukses mengambil data',
                'tryout' => $getTest,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show($tryout_uuid)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $tryout = StudentTryout::select('uuid', 'score', 'package_test_uuid', 'data_question')
                ->where([
                    'user_uuid' => $user->uuid,
                    'uuid' => $tryout_uuid,
                ])->first();

            if ($tryout == null) {
                return response()->json([
                    'message' => "Tes tidak ditemukan",
                ], 404);
            }

            $getTest = TryoutSegmentTest::select(
                'uuid',
                'test_uuid',
                'attempt',
                'duration'
            )
                ->where(['uuid' => $tryout->package_test_uuid])
                ->first();

            if (!$getTest) {
                return response()->json([
                    'message' => "Tes tidak ditemukan",
                ], 404);
            }

            $checkTestIsPurchasedOrMembership = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid);

            if ($checkTestIsPurchasedOrMembership != null) {
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

                    if ($answer->is_correct) {
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
                    'discussion' => $get_question->discussion,
                    'max_answers' => $get_question->max_answers,
                    'reference' => $get_question->reference,
                    'answers' => $answers,
                    'status' => isset($data->status) ? $data->status : "",
                ];
            }

            return response()->json([
                'message' => 'Sukses mengambil data',
                'score' => $tryout->score,
                'questions' => $questions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function checkTestIsPurchasedOrMembership($user, $tryout_segment_test)
    {
        // cek
        $check_tryout_segment_test = TryoutSegmentTest::where([
            'uuid' => $tryout_segment_test,
        ])->first();

        if ($check_tryout_segment_test == null) {
            return response()->json([
                'message' => 'Sub tryout tidak ditemukan',
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

        if (count($package_uuids) <= 0) {
            return response()->json([
                'message' => "Paket tes tidak ditemukan",
            ]);
        }

        // cek apakah user pernah membeli lifetime package tersebut
        $check_purchased_package = PurchasedPackage::where([
            "user_uuid" => $user->uuid,
        ])->whereIn("package_uuid", $package_uuids)->first();

        // jika ternyata tidak ada, maka sekarang cek di membership
        if ($check_purchased_package == null) {
            $check_membership_package = MembershipHistory::where([
                "user_uuid" => $user->uuid,
            ])
                ->whereDate('expired_date', '>', now())
                ->whereIn("package_uuid", $package_uuids)->first();

            if ($check_membership_package == null) {
                return response()->json([
                    'message' => 'Kamu tidak dapat mengakses paket ini',
                ]);
            }
        }

        return null;
    }

    public function takeTest($tryout_uuid)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $getTest = TryoutSegmentTest::with('test')->select(
                'uuid',
                'test_uuid',
                'attempt',
                'duration',
                'max_point',
                'duration_per_question',
                'duration_type'
            )->find($tryout_uuid);

            if (!$getTest) {
                return response()->json(['message' => "Tes tidak ditemukan"], 404);
            }

            // check purchase
            if ($res = $this->checkTestIsPurchasedOrMembership($user, $getTest->uuid)) {
                return $res;
            }

            // check max attempt
            if ($res = $this->checkTestMaxAttempt($user, $getTest)) {
                return $res;
            }

            // get session
            $sessionTest = $this->checkTestSession($user, $getTest);

            if (!$sessionTest instanceof SessionTest) {
                return response()->json(['message' => "Gagal membuat sesi tes"], 500);
            }

            $data_questions = json_decode($sessionTest->data_question, true);

            if (!is_array($data_questions) || count($data_questions) === 0) {
                $data_questions = $this->buildDataQuestion($getTest->test_uuid);
                $sessionTest->data_question = json_encode($data_questions);
                $sessionTest->save();
            }

            // 1 query only
            $questionUuids = array_column($data_questions, 'question_uuid');

            $questionsMap = Question::whereIn('uuid', $questionUuids)
                ->with('answers')
                ->get()
                ->keyBy('uuid');

            $questions = [];

            foreach ($data_questions as $data) {
                $q = $questionsMap[$data['question_uuid']] ?? null;
                if (!$q) continue;

                // optimize answer selection check
                $selected = array_flip($data['answer_uuid'] ?? []);

                $answers = [];
                foreach ($q->answers as $answer) {
                    $answers[] = [
                        'answer_uuid'  => $answer->uuid,
                        'answer'       => $answer->answer,
                        'image'        => $answer->image,
                        'is_selected'  => isset($selected[$answer->uuid]) ? 1 : 0,
                    ];
                }

                $questions[] = [
                    'question_uuid'       => $data['question_uuid'],
                    'status'              => $data['status'],
                    'title'               => $q->title,
                    'question_type'       => $q->question_type,
                    'question'            => $q->question,
                    'file_path'           => $q->file_path,
                    'url_path'            => $q->url_path,
                    'type'                => $q->type,
                    'hint'                => $q->hint,
                    'discussion'          => $q->discussion,
                    'max_answers'         => $q->max_answers,
                    'reference'           => $q->reference,
                    'answers'             => $answers,
                    'timer'               => $q->timer,
                ];
            }

            $test = [
                'session_uuid'         => $sessionTest->uuid,
                'duration_left'        => $sessionTest->duration_left,
                'test_uuid'            => $getTest->test_uuid,
                'duration_per_question' => $getTest->duration_per_question,
                'duration_type'        => $getTest->duration_type,
                'opening_audio' => $getTest->test->opening_audio,
                'audio_test' => $getTest->test->audio_test,
                'questions'            => $questions,
            ];

            return response()->json([
                'message' => "Sukses mengambil data",
                'question' => $test,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    public function checkTestMaxAttempt($user, $test)
    {
        $studentTest = StudentTryout::where([
            'user_uuid' => $user->uuid,
            'package_test_uuid' => $test->uuid,
        ])->count();

        if ($studentTest >= $test->attempt) {
            return response()->json([
                'success' => false,
                'message' => "Kamu telah memenuhi maksimum percobaan",
            ]);
        }
        return null;
    }

    public function checkTestSession($user, $test)
    {
        $sessionTest = SessionTest::where([
            'user_uuid' => $user->uuid,
            'package_test_uuid' => $test->uuid,
            'type_test' => 'tryout',
        ])->first();

        if ($sessionTest == null) {
            $sessionTest = $this->createTestSession($user, $test);
        }

        return $sessionTest;
    }

    /**
     * Bangun struktur data_question awal dari daftar soal pada sebuah test.
     * Mengembalikan array kosong bila test tidak ditemukan / belum punya soal.
     */
    public function buildDataQuestion($test_uuid)
    {
        $data_question = [];
        $get_test = Test::where([
            'uuid' => $test_uuid
        ])->with(['questions'])->first();

        if (!$get_test) {
            return $data_question;
        }

        foreach ($get_test->questions as $index => $data) {
            $data_question[] = [
                'question_uuid' => $data->question_uuid,
                'answer_uuid' => [],
                'answer_text' => '',
                'status' => '',
            ];
        }

        return $data_question;
    }

    public function createTestSession($user, $test)
    {
        try {
            $data_question = $this->buildDataQuestion($test->test_uuid);

            $sessionTest = SessionTest::create([
                'user_uuid' => $user->uuid,
                'duration_left' => $test->duration,
                'package_test_uuid' => $test->uuid,
                'type_test' => 'tryout',
                'test_uuid' => $test->test_uuid,
                'data_question' => json_encode($data_question),
            ]);

            return $sessionTest;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    // public function getUserTryoutAnalytic($tryout_uuid)
    // {
    //     $tryout = Tryout::where([
    //         'uuid' => $tryout_uuid
    //     ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

    //     if($tryout == null) {
    //         return response()->json([
    //             'message' => "Data not found",
    //         ], 404);
    //     }

    //     $list_score_per_segment = [];
    //     $segment_results=[];
    //     foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
    //         $list_score=[];
    //         $formattedResult=[];
    //         $countSegment = 0;
    //         foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
    //             $countSegment += 1;
    //             $packageTestUuid = $tryout_segment_test['uuid'];
    //             $maxPoint = $tryout_segment_test['max_point'] ?? 0;

    //             // Fetch corresponding records in student_tryouts
    //             $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.uuid', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
    //                 ->where('student_tryouts.user_uuid', auth()->user()->uuid)
    //                 ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
    //                 ->orderBy('student_tryouts.created_at')
    //                 ->get();
    //             // Process each attempt for the test
    //             $attemptsResult = [];
    //             $first_score = 0;

    //             foreach ($attemptsData as $attemptData) {
    //                 $first_score = $attemptsData[0]['score'];
    //                 $percentage = $attemptData->score ? ($attemptData->score / $maxPoint) * 100 : 0;
    //                 $attemptsResult[] = [
    //                     'attempt_uuid' => $attemptData->uuid,
    //                     'package_test_uuid' => $attemptData->package_test_uuid,
    //                     'score' => $attemptData->score ?: 0,
    //                     'percentage' => $percentage,
    //                 ];
    //             }
    //             $list_score[] = $first_score;

    //             // Add the test data with attempts to the result
    //             $formattedResult[] = [
    //                 'tryout_segment_test_uuid' => $tryout_segment_test['uuid'],
    //                 'test_name' => $tryout_segment_test['test']['title'],
    //                 'max_point' => $maxPoint,
    //                 'attempts' => $attemptsResult,
    //             ];
    //         }
    //         // Menghitung total nilai
    //         $total = array_sum($list_score);

    //         if($countSegment <= 0){
    //             $countSegment = 1;
    //         }

    //         // Menghitung rata-rata
    //         $average = $total / $countSegment;

    //         $list_score_per_segment[] = $average;
    //         $segment_results[] = [
    //             "tryout_segment_uuid" => $tryout_segment['uuid'],
    //             'segment_name' => $tryout_segment['title'],
    //             'segment_score' => $average,
    //             'segment_result' => $formattedResult,
    //         ];
    //     }

    //     $count = count($list_score_per_segment);

    //     // Menghitung total nilai
    //     $total = array_sum($list_score_per_segment);

    //     // Menghitung rata-rata
    //     $average = $total / $count;

    //     $tryout_result = [
    //         "tryout_uuid" => $tryout_uuid,
    //         'tryout_name' => $tryout['title'],
    //         'score' => intval($average),
    //         'tryout_result' => $segment_results,
    //     ];

    //     return response()->json([
    //         'message' => 'Success get user tryout analytics',
    //         'status' => true,
    //         'data' => $tryout_result,
    //     ], 200);
    // }


    public function getUserTryoutAnalytic($tryout_uuid)
    {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if ($tryout == null) {
            return response()->json([
                'message' => "Data tidak ditemukan",
            ], 404);
        }

        $student_attempts = [];
        $do_repeat = true;
        $attempt = 1;
        $testPotensi = false;
        while ($do_repeat) {
            $do_repeat = false;
            $list_score_per_segment = [];
            $segment_results = [];
            foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
                $testPotensi = collect($tryout_segment['tryoutSegmentTests'])->every(function ($segment) {
                    return $segment->test->test_type === "Tes Potensi" ? true : false;
                });
                $list_score = [];
                $formattedResult = [];
                $countSegment = 0;
                foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                    $countSegment += 1;
                    $packageTestUuid = $tryout_segment_test['uuid'];
                    $maxPoint = $tryout_segment_test['max_point'] ?? 0;

                    // Fetch corresponding records in student_tryouts
                    $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.uuid', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
                        ->where('student_tryouts.user_uuid', auth()->user()->uuid)
                        ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
                        ->where('student_tryouts.attempt', $attempt)
                        // ->orderBy('student_tryouts.created_at')
                        ->first();
                    // Process each attempt for the test
                    $attemptResult = null;
                    $first_score = 0;

                    if ($attemptsData) {
                        $do_repeat = true;
                        $first_score = $attemptsData->score;
                        $percentage = $attemptsData->score ? ($attemptsData->score / $maxPoint) * 100 : 0;
                        $passing_score = $tryout_segment_test['passing_score'] ?? 0;
                        $status = $attemptsData->score >= $passing_score ? 'passed' : 'failed';

                        $pendingEssays = StudentQuestionGrade::where('student_quiz_uuid', $attemptsData->uuid)
                            ->where('status', 'pending')
                            ->count();

                        $attemptResult = [
                            'attempt_uuid' => $attemptsData->uuid,
                            'package_test_uuid' => $attemptsData->package_test_uuid,
                            'score' => $attemptsData->score ?: 0,
                            'passing_score' => $passing_score,
                            'passing_score2' => $tryout_segment_test,
                            'percentage' => $percentage,
                            'status' => $status,
                            'pending_essays' => $pendingEssays,
                            'has_pending_review' => $pendingEssays > 0,
                        ];
                    }

                    $list_score[] = $first_score;

                    // Add the test data with attempts to the result
                    $formattedResult[] = [
                        'tryout_segment_test_uuid' => $tryout_segment_test['uuid'],
                        'test_name' => $tryout_segment_test['test']['student_title_display'] ? $tryout_segment_test['test']['student_title_display'] : $tryout_segment_test['test']['title'],
                        'max_point' => $maxPoint,
                        'attempt' => $attemptResult,
                    ];
                }
                // Menghitung total nilai
                $total = array_sum($list_score);

                if ($countSegment <= 0) {
                    $countSegment = 1;
                }

                // Menghitung rata-rata
                $average = $total / $countSegment;

                $list_score_per_segment[] = $average;
                $totalScore = $total;
                $totalScore += $testPotensi ? 200 : 0;
                $segment_results[] = [
                    "tryout_segment_uuid" => $tryout_segment['uuid'],
                    'segment_name' => $tryout_segment['title'],
                    'segment_score' => intval($totalScore),
                    'segment_result' => $formattedResult,
                ];
            }


            $count = count($list_score_per_segment);

            // Menghitung total nilai
            $total = array_sum($list_score_per_segment);

            // Menghitung rata-rata
            $average = $total / $count;
            $testPotensi = collect($tryout_segment['tryoutSegmentTests'])->every(function ($segment) {
                return $segment->test->test_type === "Tes Potensi" ? true : false;
            });
            $totalScore = $total;
            $totalScore += $testPotensi ? 200 : 0;
            $tryout_result = [
                "tryout_uuid" => $tryout_uuid,
                'tryout_name' => $tryout['title'],
                'score' => intval($total),
                'totalScore' => intval($totalScore),
                'tryout_result' => $segment_results,
            ];


            $student_attempts[] = [
                'title' => 'Percobaan ' . $attempt,
                'result' => $tryout_result,
            ];
            $attempt += 1;
        }

        if (count($student_attempts) > 1) {
            array_pop($student_attempts);
        }



        return response()->json([
            'message' => 'Berhasil mengambil data analitik',
            'status' => true,
            'data' => $student_attempts,
        ], 200);
    }

    public function getDesiredUserTryoutAnalytic($user_uuid, $tryout_uuid)
    {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if ($tryout == null) {
            return response()->json([
                'message' => "Data tidak ditemukan",
            ], 404);
        }

        $student_attempts = [];
        $do_repeat = true;
        $attempt = 1;
        while ($do_repeat) {
            $do_repeat = false;
            $list_score_per_segment = [];
            $segment_results = [];
            foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
                $list_score = [];
                $formattedResult = [];
                $countSegment = 0;
                foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                    $countSegment += 1;
                    $packageTestUuid = $tryout_segment_test['uuid'];
                    $maxPoint = $tryout_segment_test['max_point'] ?? 0;

                    // Fetch corresponding records in student_tryouts
                    $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.uuid', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
                        ->where('student_tryouts.user_uuid', $user_uuid)
                        ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
                        ->where('student_tryouts.attempt', $attempt)
                        // ->orderBy('student_tryouts.created_at')
                        ->first();
                    // Process each attempt for the test
                    $attemptResult = null;
                    $first_score = 0;

                    if ($attemptsData) {
                        $do_repeat = true;
                        $first_score = $attemptsData->score;
                        $percentage = $attemptsData->score ? ($attemptsData->score / $maxPoint) * 100 : 0;
                        $passing_score = $tryout_segment_test['passing_score'] ?? 0;
                        $status = $attemptsData->score >= $passing_score ? 'passed' : 'failed';
                        $attemptResult = [
                            'attempt_uuid' => $attemptsData->uuid,
                            'package_test_uuid' => $attemptsData->package_test_uuid,
                            'score' => $attemptsData->score ?: 0,
                            'percentage' => $percentage,
                            'passing_score' => $passing_score,
                            'passing_score2' => $tryout_segment_test,
                            'status' => $status,
                        ];
                    }

                    $list_score[] = $first_score;

                    // Add the test data with attempts to the result
                    $formattedResult[] = [
                        'tryout_segment_test_uuid' => $tryout_segment_test['uuid'],
                        'test_name' => $tryout_segment_test['test']['title'],
                        'max_point' => $maxPoint,
                        'attempt' => $attemptResult,
                    ];
                }
                // Menghitung total nilai
                $total = array_sum($list_score);

                if ($countSegment <= 0) {
                    $countSegment = 1;
                }

                // Menghitung rata-rata
                $average = $total / $countSegment;

                $list_score_per_segment[] = $average;
                $segment_results[] = [
                    "tryout_segment_uuid" => $tryout_segment['uuid'],
                    'segment_name' => $tryout_segment['title'],
                    'segment_score' => intval($total),
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
                'score' => intval($total),
                'tryout_result' => $segment_results,
            ];


            $student_attempts[] = [
                'title' => 'Percobaan ' . $attempt,
                'result' => $tryout_result,
            ];
            $attempt += 1;
        }

        if (count($student_attempts) > 1) {
            array_pop($student_attempts);
        }



        return response()->json([
            'message' => 'Berhasil mengambil data analitik',
            'status' => true,
            'data' => $student_attempts,
        ], 200);
    }

    public function getLeaderboard($tryout_uuid)
    {
        $tryout = Tryout::with([
            'tryoutSegments.tryoutSegmentTests.test'
        ])->where('uuid', $tryout_uuid)->first();

        if (!$tryout) {
            return response()->json([
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        /**
         * 1️⃣ Ambil semua package_test_uuid
         */
        $packageTestUuids = [];
        foreach ($tryout->tryoutSegments as $segment) {
            foreach ($segment->tryoutSegmentTests as $test) {
                $packageTestUuids[] = $test->uuid;
            }
        }
        $packageTestUuids = array_values(array_unique($packageTestUuids));

        /**
         * 2️⃣ Ambil SEMUA student_tryouts (ATTEMPT PERTAMA)
         */
        $studentTryouts = StudentTryout::whereIn('package_test_uuid', $packageTestUuids)
            ->where('attempt', 1)
            ->with('user')
            ->get();

        /**
         * 3️⃣ Group: user → package_test
         */
        $grouped = [];
        foreach ($studentTryouts as $st) {
            $grouped[$st->user_uuid][$st->package_test_uuid] = $st;
        }

        $leaderboard = [];

        /**
         * 4️⃣ HITUNG SCORE (SAMA DENGAN ANALYTIC)
         */
        foreach ($grouped as $userUuid => $tests) {

            $totalScore = 0;

            foreach ($tryout->tryoutSegments as $segment) {

                $segmentTotal = 0;
                $isTesPotensi = true;

                foreach ($segment->tryoutSegmentTests as $test) {

                    if ($test->test->test_type !== 'Tes Potensi') {
                        $isTesPotensi = false;
                    }

                    $segmentTotal += isset($tests[$test->uuid])
                        ? (int) $tests[$test->uuid]->score
                        : 0;
                }

                // ✅ BONUS FLAT +200
                if ($isTesPotensi) {
                    $segmentTotal += 200;
                }

                // ❗ TIDAK ADA AVERAGE
                $totalScore += $segmentTotal;
            }

            /**
             * Ambil user
             */
            $user = null;
            foreach ($tests as $t) {
                if ($t->user) {
                    $user = $t->user;
                    break;
                }
            }

            $leaderboard[] = [
                'user_uuid'   => $userUuid,
                'name'        => $user ? $user->username : 'Unknown',
                'tryout_uuid' => $tryout_uuid,
                'tryout_name' => $tryout->title,
                'score'       => (int) $totalScore,
            ];
        }

        /**
         * 5️⃣ SORTING
         */
        usort($leaderboard, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        /**
         * 6️⃣ RANKING (tie-safe)
         */
        $rank = 1;
        $prevScore = null;
        foreach ($leaderboard as $i => &$row) {
            if ($prevScore !== null && $row['score'] < $prevScore) {
                $rank = $i + 1;
            }
            $row['ranking'] = $rank;
            $prevScore = $row['score'];
        }
        unset($row);

        /**
         * 7️⃣ CURRENT USER
         */
        $currentUser = [
            'uuid' => auth()->user()->uuid ?? null,
            'ranking' => null,
            'score' => null
        ];

        foreach ($leaderboard as $row) {
            if ($row['user_uuid'] === $currentUser['uuid']) {
                $currentUser['ranking'] = $row['ranking'];
                $currentUser['score'] = $row['score'];
                break;
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data leaderboard',
            'data' => [
                'currentUser' => $currentUser,
                'allLeaderboard' => $leaderboard
            ]
        ], 200);
    }
}
