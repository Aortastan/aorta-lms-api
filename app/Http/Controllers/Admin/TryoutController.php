<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Tryout;
use App\Models\TryoutSegmentTest;
use App\Models\TryoutSegment;
use App\Models\PackageTest;
use App\Models\StudentTryout;

class TryoutController extends Controller
{
    public function index(){
        $search = "";
        $status = "";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['status'])){
            $status = $_GET['status'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        $tryouts = DB::table('tryouts')
        ->select(
            'tryouts.uuid',
            'tryouts.title',
            'tryouts.status',
        );

        if($search != null){
            $tryouts->where('tryouts.title', 'LIKE', '%'.$search.'%');
        }

        if($status != null){
            $tryouts->where('tryouts.status', $status);
        }

        if($orderBy != null && $order != null){
            $orderByArray = ['title', 'status'];
            $orderArray = ['asc', 'desc'];

            if(in_array($orderBy, $orderByArray) && in_array($order, $orderArray)){
                $tryouts->orderBy('tryouts.' . $orderBy, $order);
            }
        }

        $tryouts = $tryouts->get();

        foreach ($tryouts as $index => $tryout) {
            $check_tryout = PackageTest::where([
                'test_uuid' => $tryout->uuid,
            ])->first();

            if($check_tryout){
                $tryout->deletable = false;
            }else{
                $tryout->deletable = true;
            }
        }

        return response()->json([
            'message' => 'Success get data',
            'tryouts' => $tryouts,
        ], 200);
    }

    public function show(Request $request, $uuid){
        $tryout = Tryout::select('uuid', 'title', 'status')
        ->where([
            'uuid' => $uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if(!$tryout){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $getSegments = [];
        foreach ($tryout->tryoutSegments as $key => $data) {
            $segmentTests = [];
            foreach ($data['tryoutSegmentTests'] as $key1 => $data1) {
                if($data1['passing_score'] == NULL){
                    $data1['passing_score'] = $data1['test']['passing_score'];
                }
                $segmentTests[] = [
                    'segment_test_uuid' => $data1['uuid'],
                    'test_uuid' => $data1['test_uuid'],
                    'attempt' => $data1['attempt'],
                    'duration' => $data1['duration'],
                    'max_point' => $data1['max_point'],
                    'test_type' => $data1['test']['test_type'],
                    'test_title' => $data1['test']['test_title'],
                    'test_student_title_display' => $data1['test']['student_title_display'],
                    'test_passing_score' => $data1['passing_score'],
                    'test_category' => $data1['test']['test_category'],
                    'test_status' => $data1['test']['status'],
                ];
            }
            $getSegments[] = [
                'segment_uuid' => $data['uuid'],
                'segment_title' => $data['title'],
                'segment_tests' => $segmentTests,
            ];
        }

        $response_tryout = [
            'uuid' => $tryout['uuid'],
            'title' => $tryout['title'],
            'status' => $tryout['status'],
            'tryout_segments' => $getSegments
        ];

        return response()->json([
            'message' => 'Success get data',
            'tryout' => $response_tryout,
        ], 200);
    }

    public function submit(Request $request): JsonResponse{
        $validate = [
            'title' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'title' => $request->title,
            'status' => 'Draft',
        ];

        Tryout::create($validated);

        return response()->json([
            'message' => 'Success create new tryout'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $tryout = Tryout::where(['uuid' => $uuid])->first();
        if(!$tryout){
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        if($tryout->status == "Published"){
            return response()->json([
                'message' => 'tryout already published, you cannot edit this tryout.',
            ], 422);
        }

        $validate = [
            'title' => 'required',
            'status' => 'required|in:Published,Waiting for review,Draft',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        Tryout::where(['uuid' => $uuid])->update([
            'title' => $request->title,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Success update tryout'
        ], 200);
    }

    public function addTests(Request $request, $uuid){
        $tryout = Tryout::where(['uuid' => $uuid])->with(['tryoutSegments'])->first();
        if(!$tryout){
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        if($tryout->status == "Published"){
            return response()->json([
                'message' => 'tryout already published, you cannot edit this tryout.',
            ], 422);
        }

        $validate = [
            'segments' => 'required|array',
            'segments.*.title' => 'required',
            'segments.*.tests' => 'required|array',
            'segments.*.tests.*.test_uuid' => 'required',
            'segments.*.tests.*.attempt' => 'required',
            'segments.*.tests.*.duration' => 'required',
            'segments.*.tests.*.max_point' => 'required',
            'segmentes.*.tests.*.test_passing_score' => 'required'
        ];
        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        TryoutSegment::where([
            'tryout_uuid' => $uuid
        ])->delete();
        foreach ($tryout['tryoutSegments'] as $key => $data) {
            TryoutSegmentTest::where([
                'tryout_segment_uuid' => $data['uuid'],
            ])->delete();
        }

        foreach ($request->segments as $key => $segmentData) {
            $segment = TryoutSegment::create([
                'tryout_uuid' => $uuid,
                'title' => $segmentData['title']
            ]);

            foreach ($segmentData['tests'] as $key1 => $test) {
                TryoutSegmentTest::create([
                    'tryout_segment_uuid' => $segment->uuid,
                    'test_uuid' => $test['test_uuid'],
                    'attempt' => $test['attempt'],
                    'duration' => $test['duration'],
                    'max_point' => $test['max_point'],
                    'passing_score' => $test['test_passing_score']
                ]);
            }
        }


        return response()->json([
            'message' => 'Add tests success',
        ], 200);
    }

    public function delete($uuid){
        $check_tryout = Tryout::where([
            'uuid' => $uuid,
        ])->first();

        if($check_tryout == null){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $check_package_test = PackageTest::where([
            'test_uuid' => $uuid,
        ])->first();

        if($check_package_test){
            return response()->json([
                'message' => 'This tryout has published and used in package. You can\'t delete it',
            ], 404);
        }

        $check_tryout_segment = TryoutSegment::where([
            'tryout_uuid' => $uuid,
        ])->get();

        foreach ($check_tryout_segment as $index => $segment) {
            TryoutSegmentTest::where([
                'tryout_segment_uuid' => $segment->uuid,
            ])->delete();
        }

        TryoutSegment::where([
            'tryout_uuid' => $uuid,
        ])->delete();

        return response()->json([
            'message' => 'Delete succesfully',
        ], 200);
    }

    public function getTryoutLeaderboard($tryout_uuid) {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if($tryout == null) {
            return response()->json([
                'message' => "Data tidak ditemukan",
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
                    "user_name" => $attempt->user->username ?? 'Unknown',
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
                    'segment_score' => $total,
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
                'score' => intval($total),
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

        $data['allLeaderboard'] = $tryout_result;

        return response()->json([
            'message' => 'Berhasil mengambil data leaderboard',
            'status' => true,
            'data' => $data
        ], 200);
    }

    public function getLeaderboardNew($tryout_uuid) {
        try {

            // Eager load all necessary relationships
            $tryout = Tryout::with([
                'tryoutSegments.tryoutSegmentTests.test',
                'tryoutSegments.tryoutSegmentTests.studentTryouts.user'
            ])->where('uuid', $tryout_uuid)->first();

            if (!$tryout) {
                return response()->json([
                    'message' => "Data tidak ditemukan",
                ], 404);
            }

            // Get all test UUIDs
            $tryout_segment_test_uuids = $tryout->tryoutSegments->flatMap(function ($segment) {
                return $segment->tryoutSegmentTests->pluck('uuid');
            })->toArray();

            // Get all unique users who attempted this tryout
            $users = StudentTryout::whereIn('package_test_uuid', $tryout_segment_test_uuids)
                ->with('user')
                ->get()
                ->unique('user_uuid')
                ->map(function ($attempt) {
                    return [
                        "user_uuid" => $attempt->user_uuid,
                        "user_name" => $attempt->user->username ?? 'Unknown',
                    ];
                });

            $tryout_result = [];

            foreach ($users as $user_attempt) {
                $total_score = 0;
                $segment_count = 0;

                foreach ($tryout->tryoutSegments as $tryout_segment) {
                    $segment_score = 0;
                    $test_count = 0;

                    foreach ($tryout_segment->tryoutSegmentTests as $tryout_segment_test) {
                        $maxPoint = $tryout_segment_test->max_point ?? 0;

                        // Get first attempt score
                        $first_attempt = $tryout_segment_test->studentTryouts
                            ->where('user_uuid', $user_attempt['user_uuid'])
                            ->sortBy('created_at')
                            ->first();

                        if ($first_attempt) {
                            $segment_score += $first_attempt->score ?? 0;
                            $test_count++;
                        }
                    }

                    // Calculate segment average if there are tests
                    if ($test_count > 0) {
                        $total_score += ($segment_score / max(1, $test_count));
                        $segment_count++;
                    }
                }

                // Calculate overall average if there are segments
                if ($segment_count > 0) {
                    $tryout_result[] = [
                        "user_uuid" => $user_attempt['user_uuid'],
                        "name" => $user_attempt['user_name'],
                        "tryout_uuid" => $tryout_uuid,
                        'tryout_name' => $tryout->title,
                        'score' => intval($total_score),
                    ];
                }
            }

            // Sort by score descending
            usort($tryout_result, function ($a, $b) {
                return $b['score'] - $a['score'];
            });

            // Add rankings
            $ranking = 1;
            foreach ($tryout_result as &$item) {
                $item['ranking'] = $ranking++;
            }

            $data = [
                'allLeaderboard' => $tryout_result,
            ];

            return response()->json([
                'message' => 'Berhasil mengambil data leaderboard',
                'status' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserTryoutAnalytic($tryout_uuid, $user_uuid)
    {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if($tryout == null) {
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
                    $attemptsData = StudentTryout::select('student_tryouts.score', 'student_tryouts.uuid', 'student_tryouts.package_test_uuid', 'student_tryouts.uuid as tryout_uuid', 'student_tryouts.created_at')
                        ->where('student_tryouts.user_uuid', $user_uuid)
                        ->where('student_tryouts.package_test_uuid', $tryout_segment_test['uuid'])
                        ->where('student_tryouts.attempt', $attempt)
                        // ->orderBy('student_tryouts.created_at')
                        ->first();
                    // Process each attempt for the test
                    $attemptResult = null;
                    $first_score = 0;

                    if($attemptsData){
                        $do_repeat = true;
                        $first_score = $attemptsData->score;
                        $percentage = $attemptsData->score ? ($attemptsData->score / $maxPoint) * 100 : 0;
                        $passing_score = $tryout_segment_test['test']['passing_score'] ?? 0;
                        $status = $attemptsData->score >= $passing_score ? 'passed' : 'failed';
                        $attemptResult = [
                            'attempt_uuid' => $attemptsData->uuid,
                            'package_test_uuid' => $attemptsData->package_test_uuid,
                            'score' => $attemptsData->score ?: 0,
                            'percentage' => $percentage,
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

                if($countSegment <= 0){
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

        if(count($student_attempts) > 1){
            array_pop($student_attempts);
        }

        return response()->json([
            'message' => 'Berhasil mengambil data analitik',
            'status' => true,
            'data' => $student_attempts,
        ], 200);
    }

    public function getUserTryoutAnalyticNew($tryout_uuid, $user_uuid)
    {
        try {
            // Validate user_uuid exists
            $user = User::where('uuid', $user_uuid)->first();
            if (!$user) {
                return response()->json(['message' => 'User tidak ditemukan'], 404);
            }

            $tryout = Tryout::with([
                'tryoutSegments.tryoutSegmentTests.test',
                'tryoutSegments.tryoutSegmentTests.studentTryouts' => function($q) use ($user_uuid) {
                    $q->where('user_uuid', $user_uuid)
                    ->orderBy('attempt')
                    ->orderBy('created_at');
                }
            ])->where('uuid', $tryout_uuid)->first();

            if (!$tryout) {
                return response()->json(['message' => "Tryout tidak ditemukan"], 404);
            }

            $student_attempts = [];
            $max_attempts = 10;

            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                $hasAttemptData = false;
                $segment_results = [];
                $total_score = 0;

                foreach ($tryout->tryoutSegments as $tryout_segment) {
                    $segment_score = 0;
                    $test_count = 0;
                    $formattedResult = [];

                    foreach ($tryout_segment->tryoutSegmentTests as $test) {
                        $attemptData = $test->studentTryouts->firstWhere('attempt', $attempt);

                        if (!$attemptData) continue;

                        $hasAttemptData = true;
                        $maxPoint = $test->max_point ?? 1; // Avoid division by zero
                        $score = $attemptData->score ?? 0;
                        $percentage = ($score / $maxPoint) * 100;
                        $passing_score = $test->test->passing_score ?? 0;

                        $formattedResult[] = [
                            'tryout_segment_test_uuid' => $test->uuid,
                            'test_name' => $test->test->title,
                            'max_point' => $maxPoint,
                            'attempt' => [
                                'attempt_uuid' => $attemptData->uuid,
                                'package_test_uuid' => $test->uuid,
                                'score' => $score,
                                'percentage' => $percentage,
                                'status' => $score >= $passing_score ? 'passed' : 'failed',
                            ],
                        ];

                        $segment_score += $score;
                        $test_count++;
                    }

                    $segment_results[] = [
                        "tryout_segment_uuid" => $tryout_segment->uuid,
                        'segment_name' => $tryout_segment->title,
                        'segment_score' => intval($test_count ? $segment_score : 0),
                        'segment_result' => $formattedResult,
                    ];

                    $total_score += $test_count ? $segment_score : 0;
                }

                if (!$hasAttemptData) break;

                $student_attempts[] = [
                    'title' => 'Percobaan ' . $attempt,
                    'result' => [
                        "tryout_uuid" => $tryout_uuid,
                        'tryout_name' => $tryout->title,
                        'score' => intval($total_score),
                        'tryout_result' => $segment_results,
                    ],
                ];
            }

            return response()->json([
                'message' => 'Berhasil mengambil data analitik',
                'status' => true,
                'data' => $student_attempts,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
