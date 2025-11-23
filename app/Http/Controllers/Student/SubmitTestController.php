<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use Illuminate\Http\Request;
use App\Models\SessionTest;
use App\Models\StudentQuiz;
use App\Models\StudentPretestPosttest;
use App\Models\StudentTryout;
use App\Models\Question;
use App\Models\Package;
use App\Models\PackageTest;
use App\Models\TryoutSegmentTest;
use App\Models\TryoutSegment;
use App\Models\Tryout;
use App\Models\IrtPoint;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubmitTestController extends Controller
{

    public function submitTest(Request $request, $session_uuid)
    {
        $total_submit_IRT = [10, 25, 50, 100];

        $validator = Validator::make($request->all(), [
            'duration_left' => 'required',
            'data_question' => 'required|array',
            'test_uuid'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user_session = SessionTest::where('uuid', $session_uuid)->first();
        if (!$user_session) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        // fetch test once
        $test = Test::where('uuid', $request->test_uuid)->first();

        // -----------------------
        // PRELOAD QUESTIONS + ANSWERS (avoid N+1)
        // -----------------------
        $questionUuids = collect($request->data_question)->pluck('question_uuid')->unique()->values()->all();
        $questions = Question::whereIn('uuid', $questionUuids)
            ->with('answers')
            ->get()
            ->keyBy('uuid'); // $questions[$uuid]

        // Prepare counters
        $data_question = [];
        $points = 0.0;
        $potensiPoints = 0;
        $tskkwk_points = 0.0;

        // Process each submitted question (logic unchanged)
        foreach ($request->data_question as $index => $data) {
            $qUuid = $data['question_uuid'] ?? null;
            $selectedAnswers = isset($data['answer_uuid']) && is_array($data['answer_uuid']) ? $data['answer_uuid'] : [];

            if (!isset($questions[$qUuid])) {
                // if question missing, keep behaviour by skipping (same as original)
                continue;
            }

            $get_question = $questions[$qUuid];

            // Assume correct by default if there are answers
            $is_true = count($get_question->answers) > 0 ? 1 : 0;

            $answersPayload = [];

            // Build selected map for O(1) lookups
            $selectedMap = array_flip($selectedAnswers);

            foreach ($get_question->answers as $answer) {
                $is_selected = isset($selectedMap[$answer->uuid]) ? 1 : 0;

                if ($answer->is_correct == 1 && $is_selected == 0) {
                    $is_true = 0;
                }

                if ($get_question->different_point == 0) {
                    $answersPayload[] = [
                        'answer_uuid' => $answer->uuid,
                        'is_correct'  => (int) $answer->is_correct,
                        'is_selected' => $is_selected,
                    ];
                } else {
                    if ($is_selected == 1) {
                        // subtract points for selected incorrect answers (same as original)
                        $points += abs((float) $answer->point);
                    }

                    $answersPayload[] = [
                        'answer_uuid' => $answer->uuid,
                        'is_correct'  => 1,
                        'is_selected' => $is_selected,
                    ];
                }
            }

            if ($get_question->different_point == 0 && $is_true == 1) {
                $points += (float) $get_question->point;
                $tskkwk_points += 1.0 * 1.667;
                $potensiPoints += 1;
            }

            $data_question[] = [
                'question_uuid' => $qUuid,
                'answers' => $answersPayload,
            ];
        }

        // -----------------------
        // HANDLE CREATION BASED ON TYPE
        // -----------------------
        $score = 0;

        DB::beginTransaction();
        try {
            if ($user_session->type_test == 'quiz') {
                StudentQuiz::create([
                    'data_question' => json_encode($data_question),
                    'user_uuid' => $user_session->user_uuid,
                    'lesson_quiz_uuid' => $user_session->lesson_quiz_uuid,
                    'score' => $points,
                ]);
                $score = $points;
            } elseif ($user_session->type_test == 'pretest_posttest') {
                StudentPretestPosttest::create([
                    'user_uuid' => $user_session->user_uuid,
                    'data_question' => json_encode($data_question),
                    'pretest_posttest_uuid' => $user_session->pretest_posttest_uuid,
                    'score' => $points,
                ]);
                $score = $points;
            } elseif ($user_session->type_test == 'tryout') {
                // Delegate complex tryout logic to helper (keeps controller tidy)
                $score = $this->processTryout(
                    $user_session,
                    $test,
                    $data_question,
                    $points,
                    $potensiPoints,
                    $tskkwk_points,
                    $total_submit_IRT
                );
            }

            // delete session (use model instance deletion)
            $user_session->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // preserve original style of returning message
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Test berhasil dikirim',
            'score' => $score,
        ], 200);
    }

    /**
     * Helper: processTryout
     * Keeps same logic but optimized queries and structure
     */
    protected function processTryout($user_session, $test, $data_question, $points, $potensiPoints, $tskkwk_points, $total_submit_IRT)
    {
        // 1) Load TryoutSegmentTest with relations to avoid repeated queries
        $segmentTest = TryoutSegmentTest::with([
            'segment',
            'segment.tryout',
        ])->where('uuid', $user_session->package_test_uuid)->first();

        if (!$segmentTest) {
            // matches original behavior
            return response()->json(['message' => 'Sub tryout tidak ditemukan']);
        }

        $tryoutSegment = $segmentTest->segment;
        $tryout = $tryoutSegment->tryout;

        // 2) Find the package via PackageTest (original logic)
        $packageTest = PackageTest::where('test_uuid', $tryout->uuid)->first();
        $package = $packageTest ? Package::where('uuid', $packageTest->package_uuid)->first() : null;

        // Prepare result
        $score = 0;

        // Pre-calc attemptCount once
        $attemptCount = StudentTryout::where('user_uuid', $user_session->user_uuid)
            ->where('package_test_uuid', $user_session->package_test_uuid)
            ->count();

        if ($test->test_type == 'TSKKWK') {
            StudentTryout::create([
                'data_question' => json_encode($data_question),
                'user_uuid' => $user_session->user_uuid,
                'package_uuid' => $package ? $package->uuid : null,
                'package_test_uuid' => $user_session->package_test_uuid,
                'attempt' => $attemptCount + 1,
                'score' => $tskkwk_points,
            ]);
            $score = $tskkwk_points;
            return $score;
        }

        if ($test->test_type == 'Tes Potensi') {
            StudentTryout::create([
                'data_question' => json_encode($data_question),
                'user_uuid' => $user_session->user_uuid,
                'package_uuid' => $package ? $package->uuid : null,
                'package_test_uuid' => $user_session->package_test_uuid,
                'attempt' => $attemptCount + 1,
                'score' => round(($potensiPoints / 40) * 200),
            ]);
            $score = round(($potensiPoints / 40) * 200);
            return $score;
        }

        if ($test->test_type == 'IRT') {
            // Check existing IRT point record
            $check_irt_point = IrtPoint::where('package_test_uuid', $user_session->package_test_uuid)->first();

            if ($check_irt_point) {
                // create student_tryout then recalc only for that user (original behavior)
                $student_tryout = StudentTryout::create([
                    'data_question' => json_encode($data_question),
                    'user_uuid' => $user_session->user_uuid,
                    'package_uuid' => $package ? $package->uuid : null,
                    'package_test_uuid' => $user_session->package_test_uuid,
                    'attempt' => $attemptCount + 1,
                    'score' => $points,
                ]);

                // recalculate points for new attempt only
                $this->RecalculatePoint($check_irt_point, [$student_tryout]);

                // Efficiently get latestAttempts (MAX(id) per user) via grouped subquery
                $package_test_uuid = $user_session->package_test_uuid;
                $latestIds = DB::table('student_tryouts')
                    ->select(DB::raw('MAX(id) as id'))
                    ->where('package_test_uuid', $package_test_uuid)
                    ->groupBy('user_uuid')
                    ->pluck('id')
                    ->toArray();

                $latestAttempts = StudentTryout::whereIn('id', $latestIds)->get();

                $allStudentAttempts = StudentTryout::where('package_test_uuid', $package_test_uuid)->get();

                foreach ($total_submit_IRT as $total_submiter) {
                    if ($total_submiter > $check_irt_point->total_submit) {
                        if (count($latestAttempts) >= $total_submiter) {
                            $this->calculateIRT($package_test_uuid, $user_session);
                            $this->RecalculatePoint($check_irt_point, $allStudentAttempts);
                        }
                    }
                }

                $score = $points;
                return $score;
            } else {
                // No IRT point yet
                $package_test_uuid = $user_session->package_test_uuid;

                // Efficient latest attempts IDs
                $latestIds = DB::table('student_tryouts')
                    ->select(DB::raw('MAX(id) as id'))
                    ->where('package_test_uuid', $package_test_uuid)
                    ->groupBy('user_uuid')
                    ->pluck('id')
                    ->toArray();

                $latestAttempts = StudentTryout::whereIn('id', $latestIds)->get();

                if (count($latestAttempts) > $total_submit_IRT[0]) {
                    $this->calculateIRT($package_test_uuid, $user_session);
                    $allStudentAttempts = StudentTryout::where('package_test_uuid', $package_test_uuid)->get();

                    $check_irt_point = IrtPoint::where('package_test_uuid', $package_test_uuid)->first();
                    $this->RecalculatePoint($check_irt_point, $allStudentAttempts);
                }

                // create student tryout
                StudentTryout::create([
                    'data_question' => json_encode($data_question),
                    'user_uuid' => $user_session->user_uuid,
                    'package_uuid' => $package ? $package->uuid : null,
                    'package_test_uuid' => $user_session->package_test_uuid,
                    'attempt' => $attemptCount + 1,
                    'score' => $points,
                ]);
                $score = $points;
                return $score;
            }
        }

        // default fallback (other test types)
        StudentTryout::create([
            'data_question' => json_encode($data_question),
            'user_uuid' => $user_session->user_uuid,
            'package_uuid' => $package ? $package->uuid : null,
            'package_test_uuid' => $user_session->package_test_uuid,
            'attempt' => $attemptCount + 1,
            'score' => $points,
        ]);
        $score = $points;
        return $score;
    }

    /**
     * RecalculatePoint optimized: decode JSON once per tryout and update in batch loop
     */
    public function RecalculatePoint($check_irt_point, $student_tryout)
    {
        // ensure we have data array for points per question
        $irtPoints = is_string($check_irt_point->data_question)
            ? json_decode($check_irt_point->data_question, true)
            : (array)$check_irt_point->data_question;

        foreach ($student_tryout as $tryout) {
            $points = 0;
            $data_question = is_string($tryout->data_question)
                ? json_decode($tryout->data_question, true)
                : (array)$tryout->data_question;

            foreach ($data_question as $qIndex => $question) {
                if (!isset($irtPoints[$qIndex])) continue;
                $pointForQuestion = (int)$irtPoints[$qIndex];

                foreach ($question['answers'] as $answer) {
                    if ((isset($answer['is_selected']) && $answer['is_selected'] == 1)
                        && (isset($answer['is_correct']) && $answer['is_correct'] == 1)
                    ) {
                        $points += $pointForQuestion;
                    }
                }
            }

            StudentTryout::where('uuid', $tryout->uuid)
                ->update(['score' => $points]);
        }
    }

    /**
     * calculateIRT optimized:
     * - uses MAX(id) per user subquery to get latest attempts
     * - single-pass computation of wrong answers
     * - avoid repeated array_sum inside loop
     */
    public function calculateIRT($package_test_uuid, $user_session)
    {
        $get_package_test = TryoutSegmentTest::where('uuid', $package_test_uuid)->first();
        $get_package = $get_package_test ? Package::where('uuid', $get_package_test->package_uuid)->first() : null;

        // get latest attempt ids (MAX id per user) - efficient subquery
        $latestIds = DB::table('student_tryouts')
            ->select(DB::raw('MAX(id) as id'))
            ->where('package_test_uuid', $package_test_uuid)
            ->groupBy('user_uuid')
            ->pluck('id')
            ->toArray();

        $latestAttempts = StudentTryout::whereIn('id', $latestIds)->get();

        $wrong_answers = [];
        $zero_student = false;

        // build wrong_answers count per question index
        foreach ($latestAttempts as $test) {
            $data_question = is_string($test->data_question) ? json_decode($test->data_question, true) : (array)$test->data_question;

            foreach ($data_question as $qIndex => $data) {
                if (!isset($wrong_answers[$qIndex])) $wrong_answers[$qIndex] = 0;

                $no_answer = true;
                foreach ($data['answers'] as $answer) {
                    if ((isset($answer['is_selected']) && $answer['is_selected'] == 1)
                        && (isset($answer['is_correct']) && $answer['is_correct'] == 0)
                    ) {
                        $no_answer = false;
                        $wrong_answers[$qIndex] += 1;
                    }
                }

                if ($no_answer) {
                    $wrong_answers[$qIndex] += 1;
                }

                if ($wrong_answers[$qIndex] <= 0) {
                    $zero_student = true;
                }
            }
        }

        if (empty($wrong_answers)) {
            // fallback: avoid division by zero
            $point_per_soal = [];
        } else {
            $total_point = $get_package_test->max_point;
            $scale_factor = 1;

            // if zero_student true, we'll add +1 to each count
            if ($zero_student) {
                foreach ($wrong_answers as $k => $v) {
                    $wrong_answers[$k] = $v + 1;
                }
            }

            $sumWrong = array_sum($wrong_answers);
            $point_per_soal = [];

            foreach ($wrong_answers as $soal => $jumlah_salah) {
                // preserve integer cast like original code
                $point = $total_point * ($jumlah_salah / $sumWrong) * $scale_factor;
                $point_per_soal[$soal] = intval($point);
            }

            // adjust to match total_point if rounding dropped sum
            $total_point_after_adjustment = array_sum($point_per_soal);
            if ($total_point_after_adjustment < $total_point) {
                $soal_tertinggi = array_search(max($point_per_soal), $point_per_soal);
                $selisih = $total_point - $total_point_after_adjustment;
                $point_per_soal[$soal_tertinggi] += $selisih;
                $total_point_after_adjustment = array_sum($point_per_soal);
            }
        }

        // upsert IrtPoint (update or create)
        IrtPoint::updateOrCreate(
            ['package_test_uuid' => $user_session->package_test_uuid],
            [
                'total_submit' => count($latestAttempts),
                'data_question' => json_encode($point_per_soal),
                'package_test_uuid' => $user_session->package_test_uuid,
            ]
        );
    }
}
