<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MultiSheetExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Question;
use App\Models\Tryout;
use App\Models\TryoutSegmentTest;
use App\Models\TryoutSegment;
use App\Models\PackageTest;
use App\Models\StudentTryout;
use App\Models\Answer;
use App\Models\StudentQuestionGrade;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RankingQuestionExport;
use App\Exports\StudentTryoutExport;
use Log;
use ZipArchive;



class TryoutController extends Controller
{
    public function index()
    {
        $search = "";
        $status = "";
        $orderBy = "";
        $order = "";

        if (isset($_GET['search'])) {
            $search = $_GET['search'];
        }

        if (isset($_GET['status'])) {
            $status = $_GET['status'];
        }

        if (isset($_GET['orderBy']) && isset($_GET['order'])) {
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        $tryouts = DB::table('tryouts')
            ->select(
                'tryouts.uuid',
                'tryouts.title',
                'tryouts.status',
            );

        if ($search != null) {
            $tryouts->where('tryouts.title', 'LIKE', '%' . $search . '%');
        }

        if ($status != null) {
            $tryouts->where('tryouts.status', $status);
        }

        if ($orderBy != null && $order != null) {
            $orderByArray = ['title', 'status'];
            $orderArray = ['asc', 'desc'];

            if (in_array($orderBy, $orderByArray) && in_array($order, $orderArray)) {
                $tryouts->orderBy('tryouts.' . $orderBy, $order);
            }
        }

        $tryouts = $tryouts->get();

        // Kumpulan test_uuid yang punya minimal satu soal bertipe essay
        $essayTestUuids = DB::table('question_tests')
            ->join('questions', 'questions.uuid', '=', 'question_tests.question_uuid')
            ->where('questions.question_type', 'essay')
            ->pluck('question_tests.test_uuid')
            ->unique()
            ->flip();

        // Peta tryout_uuid => daftar test_uuid yang ada di dalamnya
        $tryoutTestsMap = DB::table('tryout_segments')
            ->join('tryout_segment_tests', 'tryout_segment_tests.tryout_segment_uuid', '=', 'tryout_segments.uuid')
            ->select('tryout_segments.tryout_uuid', 'tryout_segment_tests.test_uuid')
            ->get()
            ->groupBy('tryout_uuid');

        foreach ($tryouts as $index => $tryout) {
            $check_tryout = PackageTest::where([
                'test_uuid' => $tryout->uuid,
            ])->first();

            if ($check_tryout) {
                $tryout->deletable = false;
            } else {
                $tryout->deletable = true;
            }

            // Tryout dianggap mengandung essay jika salah satu test-nya punya soal essay
            $hasEssay = false;
            foreach (($tryoutTestsMap[$tryout->uuid] ?? []) as $segmentTest) {
                if ($essayTestUuids->has($segmentTest->test_uuid)) {
                    $hasEssay = true;
                    break;
                }
            }
            $tryout->has_essay = $hasEssay;
        }

        return response()->json([
            'message' => 'Success get data',
            'tryouts' => $tryouts,
        ], 200);
    }

    public function show(Request $request, $uuid)
    {
        $tryout = Tryout::select('uuid', 'title', 'status')
            ->where([
                'uuid' => $uuid
            ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if (!$tryout) {
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $getSegments = [];
        foreach ($tryout->tryoutSegments as $key => $data) {
            $segmentTests = [];
            foreach ($data['tryoutSegmentTests'] as $key1 => $data1) {
                if ($data1['passing_score'] == NULL) {
                    $data1['passing_score'] = $data1['test']['passing_score'];
                }
                $segmentTests[] = [
                    'segment_test_uuid' => $data1['uuid'],
                    'test_uuid' => $data1['test_uuid'],
                    'attempt' => $data1['attempt'],
                    'duration' => $data1['duration'],
                    'duration_per_question' => $data1['duration_per_question'],
                    'duration_type' => $data1['duration_type'],
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

    public function submit(Request $request): JsonResponse
    {
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

    public function update(Request $request, $uuid): JsonResponse
    {
        $tryout = Tryout::where(['uuid' => $uuid])->first();
        if (!$tryout) {
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        // if ($tryout->status == "Published") {
        //     return response()->json([
        //         'message' => 'tryout already published, you cannot edit this tryout.',
        //     ], 422);
        // }

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

    public function addTests(Request $request, $uuid)
    {
        $tryout = Tryout::where(['uuid' => $uuid])->with(['tryoutSegments'])->first();
        if (!$tryout) {
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        // if ($tryout->status == "Published") {
        //     return response()->json([
        //         'message' => 'tryout already published, you cannot edit this tryout.',
        //     ], 422);
        // }

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

        DB::transaction(function () use ($request, $uuid) {
            $existingSegments = TryoutSegment::where('tryout_uuid', $uuid)->with('tryoutSegmentTests')->get();
            $existingSegmentUuids = $existingSegments->pluck('uuid')->toArray();

            $receivedSegmentUuids = [];
            $receivedSegmentTestUuids = [];

            foreach ($request->segments as $segmentData) {
                if (!empty($segmentData['segment_uuid'])) {
                    $receivedSegmentUuids[] = $segmentData['segment_uuid'];
                }
                if (isset($segmentData['tests']) && is_array($segmentData['tests'])) {
                    foreach ($segmentData['tests'] as $test) {
                        if (!empty($test['segment_test_uuid'])) {
                            $receivedSegmentTestUuids[] = $test['segment_test_uuid'];
                        }
                    }
                }
            }

            // Delete segments that are no longer present in the request
            $segmentsToDelete = array_diff($existingSegmentUuids, $receivedSegmentUuids);
            if (!empty($segmentsToDelete)) {
                TryoutSegmentTest::whereIn('tryout_segment_uuid', $segmentsToDelete)->delete();
                TryoutSegment::whereIn('uuid', $segmentsToDelete)->delete();
            }

            // Also delete individual segment tests that were removed within kept segments
            $existingTestUuids = $existingSegments
                ->whereNotIn('uuid', $segmentsToDelete)
                ->flatMap(function ($segment) {
                    return $segment->tryoutSegmentTests->pluck('uuid');
                })
                ->toArray();

            $testsToDelete = array_diff($existingTestUuids, $receivedSegmentTestUuids);
            if (!empty($testsToDelete)) {
                TryoutSegmentTest::whereIn('uuid', $testsToDelete)->delete();
            }

            foreach ($request->segments as $key => $segmentData) {
                $segment = null;
                $segmentUuid = $segmentData['segment_uuid'] ?? null;

                if (!empty($segmentUuid)) {
                    TryoutSegment::where('uuid', $segmentUuid)->update([
                        'title' => $segmentData['title']
                    ]);
                } else {
                    $segment = TryoutSegment::create([
                        'tryout_uuid' => $uuid,
                        'title' => $segmentData['title']
                    ]);
                    $segmentUuid = $segment->uuid;
                }

                if (isset($segmentData['tests']) && is_array($segmentData['tests'])) {
                    foreach ($segmentData['tests'] as $key1 => $test) {
                        if (!empty($test['segment_test_uuid'])) {
                            TryoutSegmentTest::where('uuid', $test['segment_test_uuid'])->update([
                                'tryout_segment_uuid' => $segmentUuid,
                                'test_uuid' => $test['test_uuid'],
                                'attempt' => $test['attempt'],
                                'duration' => $test['duration'],
                                'max_point' => $test['max_point'],
                                'passing_score' => $test['test_passing_score'],
                                'duration_per_question' => $test['duration_per_question'],
                                'duration_type' => $test['duration_type'],
                            ]);
                        } else {
                            TryoutSegmentTest::create([
                                'tryout_segment_uuid' => $segmentUuid,
                                'test_uuid' => $test['test_uuid'],
                                'attempt' => $test['attempt'],
                                'duration' => $test['duration'],
                                'max_point' => $test['max_point'],
                                'passing_score' => $test['test_passing_score'],
                                'duration_per_question' => $test['duration_per_question'],
                                'duration_type' => $test['duration_type'],
                            ]);
                        }
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Add tests success',
        ], 200);
    }

    public function delete($uuid)
    {
        $check_tryout = Tryout::where([
            'uuid' => $uuid,
        ])->first();

        if ($check_tryout == null) {
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $check_package_test = PackageTest::where([
            'test_uuid' => $uuid,
        ])->first();

        if ($check_package_test) {
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

    public function getTryoutLeaderboard($tryout_uuid)
    {
        $tryout = Tryout::where([
            'uuid' => $tryout_uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if ($tryout == null) {
            return response()->json([
                'message' => "Data tidak ditemukan",
            ], 404);
        }

        $tryout_segment_test_uuids = [];
        foreach ($tryout['tryoutSegments'] as $index => $tryout_segment) {
            $list_score = [];
            $formattedResult = [];
            $countSegment = 0;
            foreach ($tryout_segment['tryoutSegmentTests'] as $index1 => $tryout_segment_test) {
                $tryout_segment_test_uuids[] = $tryout_segment_test['uuid'];
            }
        }

        $tryout_result = [];
        $earliestAttempts = StudentTryout::select('user_uuid')
            ->whereIn('package_test_uuid', $tryout_segment_test_uuids)
            ->with(['user'])
            ->groupBy('user_uuid');
        $earliestAttempts = $earliestAttempts->groupBy('package_test_uuid')->get();
        $user_uuids = [];
        $users = [];
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

                if ($countSegment <= 0) {
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

    public function getLeaderboardNew($tryout_uuid, Request $request = null)
    {
        $segmentTestFilter = $request ? $request->query('tryout_segment_test_uuid') : request()->query('tryout_segment_test_uuid');

        try {

            // Eager load structure without data_question
            $tryout = Tryout::with([
                'tryoutSegments' => function ($query) {
                    $query->select('uuid', 'tryout_uuid', 'title');
                },
                'tryoutSegments.tryoutSegmentTests' => function ($query) use ($segmentTestFilter) {
                    if (!empty($segmentTestFilter)) {
                        $query->where('uuid', $segmentTestFilter);
                    }
                    $query->select('uuid', 'tryout_segment_uuid', 'test_uuid', 'max_point');
                },
                'tryoutSegments.tryoutSegmentTests.test' => function ($query) {
                    $query->select('uuid', 'test_type', 'title');
                }
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

            if (empty($tryout_segment_test_uuids)) {
                return response()->json([
                    'message' => 'Berhasil mengambil data leaderboard',
                    'status' => true,
                    'data' => [
                        'allLeaderboard' => [],
                    ]
                ], 200);
            }

            // Ambil hanya attempt pertama per user per package_test_uuid tanpa kolom data_question
            // Subquery untuk mendapatkan created_at terlama (attempt pertama)
            $firstAttempts = DB::table('student_tryouts as st')
                ->join(DB::raw('(SELECT user_uuid, package_test_uuid, MIN(created_at) as min_created_at FROM student_tryouts WHERE package_test_uuid IN (\'' . implode("','", array_map('addslashes', $tryout_segment_test_uuids)) . '\') GROUP BY user_uuid, package_test_uuid) as first_st'), function ($join) {
                    $join->on('st.user_uuid', '=', 'first_st.user_uuid')
                        ->on('st.package_test_uuid', '=', 'first_st.package_test_uuid')
                        ->on('st.created_at', '=', 'first_st.min_created_at');
                })
                ->leftJoin('users as u', 'u.uuid', '=', 'st.user_uuid')
                ->whereIn('st.package_test_uuid', $tryout_segment_test_uuids)
                ->select('st.user_uuid', 'st.package_test_uuid', 'st.score', 'u.username')
                ->get();

            // Kelompokkan attempt per user_uuid: [user_uuid => [package_test_uuid => score]]
            $userScores = [];
            $userNames = [];
            foreach ($firstAttempts as $attempt) {
                $userScores[$attempt->user_uuid][$attempt->package_test_uuid] = $attempt->score ?? 0;
                if (!isset($userNames[$attempt->user_uuid])) {
                    $userNames[$attempt->user_uuid] = $attempt->username ?? 'Unknown';
                }
            }

            $tryout_result = [];

            // Jika filter per TryoutSegmentTest aktif, hitung langsung per test tersebut
            if (!empty($segmentTestFilter)) {
                $targetSegmentTest = $tryout->tryoutSegments->flatMap->tryoutSegmentTests->firstWhere('uuid', $segmentTestFilter);
                $isTargetPotensi = $targetSegmentTest && (optional($targetSegmentTest->test)->test_type === "Tes Potensi" || $targetSegmentTest->test_type === "Tes Potensi");

                foreach ($userScores as $user_uuid => $scoresPerTest) {
                    if (array_key_exists($segmentTestFilter, $scoresPerTest)) {
                        $rawScore = $scoresPerTest[$segmentTestFilter];
                        $tryout_result[] = [
                            "user_uuid" => $user_uuid,
                            "name" => $userNames[$user_uuid] ?? 'Unknown',
                            "tryout_uuid" => $tryout_uuid,
                            'tryout_segment_test_uuid' => $segmentTestFilter,
                            'tryout_name' => $tryout->title,
                            'test_title' => optional($targetSegmentTest->test)->title ?? '',
                            'score' => $isTargetPotensi ? intval($rawScore) + 200 : intval($rawScore),
                        ];
                    }
                }
            } else {
                $totalTryoutSegments = count($tryout->tryoutSegments);

                foreach ($userScores as $user_uuid => $scoresPerTest) {
                    $total_score = 0;
                    $attempted_any = false;
                    $lastSegmentPotensi = false;

                    foreach ($tryout->tryoutSegments as $tryout_segment) {
                        $segment_score = 0;
                        $total_tests_in_segment = count($tryout_segment->tryoutSegmentTests);

                        foreach ($tryout_segment->tryoutSegmentTests as $tryout_segment_test) {
                            if (array_key_exists($tryout_segment_test->uuid, $scoresPerTest)) {
                                $segment_score += $scoresPerTest[$tryout_segment_test->uuid];
                                $attempted_any = true;
                            }
                        }

                        // Bagi dengan total seluruh subtes yang ada pada segmen ini (yang belum dikerjakan bernilai 0)
                        $segment_average = $segment_score / max(1, $total_tests_in_segment);
                        $total_score += $segment_average;

                        // Cek apakah semua test dalam segment ini berjenis "Tes Potensi"
                        $lastSegmentPotensi = collect($tryout_segment->tryoutSegmentTests)->every(function ($test) {
                            return optional($test->test)->test_type === "Tes Potensi" || $test->test_type === "Tes Potensi";
                        });
                    }

                    // Hanya masukkan peserta yang sudah mengerjakan minimal 1 subtes
                    if ($attempted_any && $totalTryoutSegments > 0) {
                        // Rata-rata per segmen (jika ada lebih dari 1 segmen)
                        $overall_average = $total_score / max(1, $totalTryoutSegments);

                        $tryout_result[] = [
                            "user_uuid" => $user_uuid,
                            "name" => $userNames[$user_uuid] ?? 'Unknown',
                            "tryout_uuid" => $tryout_uuid,
                            'tryout_name' => $tryout->title,
                            'score' => $lastSegmentPotensi ? round($overall_average + 200, 2) : round($overall_average, 2),
                        ];
                    }
                }
            }

            // Sort by score descending
            usort($tryout_result, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            // Add rankings
            $ranking = 1;
            foreach ($tryout_result as &$item) {
                $item['ranking'] = $ranking++;
            }
            unset($item);

            $segmentTestsList = [];
            foreach ($tryout->tryoutSegments as $seg) {
                foreach ($seg->tryoutSegmentTests as $st) {
                    $segmentTestsList[] = [
                        'uuid' => $st->uuid,
                        'segment_title' => $seg->title,
                        'test_title' => optional($st->test)->title ?? 'Test',
                    ];
                }
            }

            $data = [
                'allLeaderboard' => $tryout_result,
                'segmentTests' => $segmentTestsList,
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

    public function getEssayLeaderboard($tryout_uuid)
    {
        try {
            $tryout = Tryout::with([
                'tryoutSegments.tryoutSegmentTests.test',
                'tryoutSegments.tryoutSegmentTests.studentTryouts.user'
            ])->where('uuid', $tryout_uuid)->first();

            if (!$tryout) {
                return response()->json([
                    'message' => "Data tidak ditemukan",
                ], 404);
            }

            $tryout_segment_test_uuids = $tryout->tryoutSegments->flatMap(function ($segment) {
                return $segment->tryoutSegmentTests->pluck('uuid');
            })->toArray();

            $studentTryoutUuids = StudentTryout::whereIn('package_test_uuid', $tryout_segment_test_uuids)
                ->pluck('uuid')
                ->toArray();

            $essayAttemptUuids = StudentQuestionGrade::whereIn('student_quiz_uuid', $studentTryoutUuids)
                ->pluck('student_quiz_uuid')
                ->unique()
                ->toArray();

            $usersWithEssay = StudentTryout::whereIn('uuid', $essayAttemptUuids)
                ->pluck('user_uuid')
                ->unique()
                ->flip()
                ->toArray();

            $allUsers = StudentTryout::whereIn('package_test_uuid', $tryout_segment_test_uuids)
                ->with('user')
                ->get()
                ->unique('user_uuid');

            $pendingByUser = StudentQuestionGrade::whereIn('student_quiz_uuid', $essayAttemptUuids)
                ->where('status', 'pending')
                ->get()
                ->groupBy('user_uuid')
                ->map(function ($items) {
                    return $items->count();
                })
                ->toArray();

            $tryout_result = [];
            foreach ($allUsers as $attempt) {
                if (!isset($usersWithEssay[$attempt->user_uuid])) {
                    continue;
                }

                $total_score = 0;
                $segment_count = 0;
                foreach ($tryout->tryoutSegments as $tryout_segment) {
                    $segment_score = 0;
                    $test_count = 0;
                    foreach ($tryout_segment->tryoutSegmentTests as $tryout_segment_test) {
                        $first_attempt = $tryout_segment_test->studentTryouts
                            ->where('user_uuid', $attempt->user_uuid)
                            ->sortBy('created_at')
                            ->first();
                        if ($first_attempt) {
                            $segment_score += $first_attempt->score ?? 0;
                            $test_count++;
                        }
                    }
                    if ($test_count > 0) {
                        $total_score += ($segment_score / max(1, $test_count));
                        $segment_count++;
                    }
                }

                $tryout_result[] = [
                    "user_uuid" => $attempt->user_uuid,
                    "name" => $attempt->user->username ?? 'Unknown',
                    "tryout_uuid" => $tryout_uuid,
                    'tryout_name' => $tryout->title,
                    'score' => intval($total_score),
                    'pending_essays' => $pendingByUser[$attempt->user_uuid] ?? 0,
                ];
            }

            usort($tryout_result, function ($a, $b) {
                return $b['score'] - $a['score'];
            });

            $ranking = 1;
            foreach ($tryout_result as &$item) {
                $item['ranking'] = $ranking++;
            }

            return response()->json([
                'message' => 'Berhasil mengambil data leaderboard essay',
                'status' => true,
                'data' => [
                    'allLeaderboard' => $tryout_result,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserTryoutAnalytic($tryout_uuid, $user_uuid)
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
                        $passing_score = $tryout_segment_test['test']['passing_score'] ?? 0;
                        $status = $attemptsData->score >= $passing_score ? 'passed' : 'failed';

                        $essayCount = StudentQuestionGrade::where('student_quiz_uuid', $attemptsData->uuid)->count();
                        $pendingEssays = $essayCount > 0
                            ? StudentQuestionGrade::where('student_quiz_uuid', $attemptsData->uuid)
                                ->where('status', 'pending')
                                ->count()
                            : 0;

                        $attemptResult = [
                            'attempt_uuid' => $attemptsData->uuid,
                            'package_test_uuid' => $attemptsData->package_test_uuid,
                            'score' => $attemptsData->score ?: 0,
                            'percentage' => $percentage,
                            'status' => $status,
                            'has_essay' => $essayCount > 0,
                            'essay_count' => $essayCount,
                            'pending_essays' => $pendingEssays,
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
                'tryoutSegments.tryoutSegmentTests.studentTryouts' => function ($q) use ($user_uuid) {
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

    public function getTryout($tryout_uuid, $user_uuid)
    {
        try {
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
            $pretest_posttests = StudentTryout::select('uuid', 'score')
                ->where([
                    'user_uuid' => $user_uuid,
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

    public function adminReview($tryout_uuid, $user_uuid)
    {
        try {
            $tryout = StudentTryout::select('uuid', 'score', 'package_test_uuid', 'data_question')
                ->where([
                    'user_uuid' => $user_uuid,
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

    public function rankingQuestion($tryout_uuid, Request $request)
    {
        $sortBy = $request->query('sort');
        $segmentTestFilter = $request->query('tryout_segment_test_uuid');

        try {
            $tryout = Tryout::where('uuid', $tryout_uuid)
                ->with([
                    'tryoutSegments.tryoutSegmentTests' => function ($q) use ($segmentTestFilter) {
                        if (!empty($segmentTestFilter)) {
                            $q->where('uuid', $segmentTestFilter);
                        }
                    },
                    'tryoutSegments.tryoutSegmentTests.test'
                ])
                ->first();

            if (!$tryout) {
                return response()->json([
                    'message' => "Tes tidak ditemukan",
                ], 404);
            }

            // Ambil semua tryout_segment_test yang relevan
            $segmentTests = $tryout->tryoutSegments->flatMap->tryoutSegmentTests;
            $segmentTestUuids = $segmentTests->pluck('uuid')->toArray();

            if (empty($segmentTestUuids)) {
                return response()->json([
                    'message' => 'Sukses ambil data ranking soal',
                    'data' => [],
                ], 200);
            }

            // Ambil urutan nomor soal dari tabel question_tests per test_uuid
            $testUuids = $segmentTests->pluck('test_uuid')->filter()->unique()->toArray();
            $questionTests = DB::table('question_tests')
                ->whereIn('test_uuid', $testUuids)
                ->orderBy('id', 'asc')
                ->select('test_uuid', 'question_uuid', 'id')
                ->get();

            // Peta urutan nomor soal per test_uuid: [test_uuid => [question_uuid => nomor_soal]]
            $questionNumberByTest = [];
            foreach ($questionTests->groupBy('test_uuid') as $tUuid => $qItems) {
                $no = 1;
                foreach ($qItems as $qItem) {
                    if (!isset($questionNumberByTest[$tUuid][$qItem->question_uuid])) {
                        $questionNumberByTest[$tUuid][$qItem->question_uuid] = $no++;
                    }
                }
            }

            // Peta package_test_uuid => test_uuid dan info segment test
            $segmentTestInfo = [];
            foreach ($segmentTests as $st) {
                $segmentTestInfo[$st->uuid] = [
                    'tryout_segment_test_uuid' => $st->uuid,
                    'test_uuid' => $st->test_uuid,
                    'test_title' => optional($st->test)->title ?? '',
                ];
            }

            // Ambil data_question hanya dari kolom data_question & package_test_uuid
            $studentTryouts = DB::table('student_tryouts')
                ->whereIn('package_test_uuid', $segmentTestUuids)
                ->whereNotNull('data_question')
                ->select('package_test_uuid', 'data_question')
                ->get();

            $summary = [];
            $allQuestionUuids = [];

            foreach ($studentTryouts as $stAttempt) {
                $decoded = json_decode($stAttempt->data_question, true);
                if (!is_array($decoded)) {
                    continue;
                }

                $packageTestUuid = $stAttempt->package_test_uuid;
                $tUuid = $segmentTestInfo[$packageTestUuid]['test_uuid'] ?? null;

                foreach ($decoded as $questionIndex => $question) {
                    if (!isset($question['question_uuid'])) {
                        continue;
                    }

                    $questionUuid = $question['question_uuid'];
                    $allQuestionUuids[$questionUuid] = true;

                    $isCorrectSelected = 0;
                    $isIncorrectSelected = 0;

                    foreach ($question['answers'] ?? [] as $ans) {
                        $isCorrect = isset($ans['is_correct']) && (int)$ans['is_correct'] === 1;
                        $isSelected = isset($ans['is_selected']) && (int)$ans['is_selected'] === 1;

                        if ($isSelected) {
                            if ($isCorrect) {
                                $isCorrectSelected++;
                            } else {
                                $isIncorrectSelected++;
                            }
                        }
                    }

                    // Tentukan nomor soal: dari tabel question_tests, atau fallback ke indeks + 1
                    $qNumber = null;
                    if ($tUuid && isset($questionNumberByTest[$tUuid][$questionUuid])) {
                        $qNumber = $questionNumberByTest[$tUuid][$questionUuid];
                    } else {
                        $qNumber = $questionIndex + 1;
                    }

                    if (!isset($summary[$questionUuid])) {
                        $summary[$questionUuid] = [
                            'question_number' => $qNumber,
                            'question_uuid' => $questionUuid,
                            'tryout_segment_test_uuid' => $packageTestUuid,
                            'test_title' => $segmentTestInfo[$packageTestUuid]['test_title'] ?? '',
                            'question' => null,
                            'total_correct_selected' => 0,
                            'total_incorrect_selected' => 0,
                            'total_participant' => 0,
                        ];
                    }

                    $summary[$questionUuid]['total_correct_selected'] += $isCorrectSelected;
                    $summary[$questionUuid]['total_incorrect_selected'] += $isIncorrectSelected;
                    $summary[$questionUuid]['total_participant'] += 1;
                }
            }

            if (!empty($allQuestionUuids)) {
                $questionMap = Question::whereIn('uuid', array_keys($allQuestionUuids))
                    ->pluck('question', 'uuid')
                    ->toArray();

                foreach ($summary as $qUuid => &$data) {
                    $data['question'] = $questionMap[$qUuid] ?? null;
                }
                unset($data);
            }

            $sorted = collect(array_values($summary));

            switch ($sortBy) {
                case 'wrong_desc':
                    $sorted = $sorted->sortByDesc('total_incorrect_selected')->values();
                    break;
                case 'correct_desc':
                    $sorted = $sorted->sortByDesc('total_correct_selected')->values();
                    break;
                case 'wrong_asc':
                    $sorted = $sorted->sortBy('total_incorrect_selected')->values();
                    break;
                case 'correct_asc':
                    $sorted = $sorted->sortBy('total_correct_selected')->values();
                    break;
                case 'number_asc':
                    $sorted = $sorted->sortBy('question_number')->values();
                    break;
                case 'number_desc':
                    $sorted = $sorted->sortByDesc('question_number')->values();
                    break;
                default:
                    $sorted = $sorted->sortByDesc('total_incorrect_selected')->values();
                    break;
            }

            return response()->json([
                'message' => 'Sukses ambil data ranking soal',
                'data' => $sorted,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function exportRankingQuestion($tryout_uuid, Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $sorting = request()->query('sort');
        $segmentTestUuid = request()->query('tryout_segment_test_uuid');
        return Excel::download(new RankingQuestionExport($tryout_uuid, $sorting, $segmentTestUuid), 'ranking-questions.xlsx');
    }

    public function exportStudentTryout($tryout_uuid, Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $user_uuid = $request->query("user_uuid");
        try {
            if (empty($user_uuid)) {
                $tryouts = Tryout::where('uuid', $tryout_uuid)->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test', 'tryoutSegments.tryoutSegmentTests.studentTryouts', 'tryoutSegments.tryoutSegmentTests.studentTryouts.user'])->get();
            } else {
                $tryouts = Tryout::where('uuid', $tryout_uuid)
                    ->with([
                        'tryoutSegments',
                        'tryoutSegments.tryoutSegmentTests',
                        'tryoutSegments.tryoutSegmentTests.test',
                        'tryoutSegments.tryoutSegmentTests.studentTryouts' => function ($query) use ($user_uuid) {
                            $query->where('user_uuid', $user_uuid);
                        },
                        'tryoutSegments.tryoutSegmentTests.studentTryouts.user',
                    ])
                    ->get();
            }
            $sheets = [];
            foreach ($tryouts as $tryout) {
                foreach ($tryout->tryoutSegments as $tryoutSegment) {
                    foreach ($tryoutSegment->tryoutSegmentTests as $tryoutSegmentTest) {
                        foreach ($tryoutSegmentTest->studentTryouts as $studentTryout) {
                            $sheets[] = new StudentTryoutExport($studentTryout, $tryoutSegmentTest, $tryout_uuid);
                        }
                    }
                }
            }
            Log::info("Processing total " . count($sheets) . " sheets.");
            return Excel::download(new MultiSheetExport($sheets), 'student-tryout.xlsx');
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 404);
        }
    }
}
