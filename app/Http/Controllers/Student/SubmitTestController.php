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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SubmitTestController extends Controller
{
    public function submitTest(Request $request, $session_uuid)
    {
        $validator = Validator::make($request->all(), [
            'duration_left' => 'required',
            'data_question' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $session_uuid) {

                $user_session = SessionTest::where(['uuid' => $session_uuid])
                    ->lockForUpdate()
                    ->first();

                if (!$user_session) {
                    return response()->json([
                        'message' => 'Session not found'
                    ], 404);
                }

                $channel = 'submit-test.' . $user_session->uuid;

                // $this->pushProgress($channel, ProgressStatus::INIT, 5, $user_session->uuid);

                $test = Test::where(['uuid' => $request->test_uuid])->first();
                $total_submit_IRT = [10, 25, 50, 100];

                // $this->pushProgress($channel, ProgressStatus::FETCHING_QUESTIONS, 10, $user_session->uuid);

                $questionUuids = collect($request->data_question)
                    ->pluck('question_uuid')
                    ->unique();

                $questions = Question::whereIn('uuid', $questionUuids)
                    ->get()
                    ->keyBy('uuid');

                $answers = Answer::whereIn('question_uuid', $questionUuids)
                    ->get()
                    ->groupBy('question_uuid');

        $data_question = [];
        $points = 0;
        $potensiPoints = 0;
        $tskkwk_points = 0;

                $total = count($request->data_question);
                $current = 0;

                foreach ($request->data_question as $data) {

                    $current++;

                    $get_question = $questions[$data['question_uuid']] ?? null;
                    if (!$get_question) continue;

                    $get_answers = $answers[$data['question_uuid']] ?? collect();

                    $is_true = 1;
                    $answers_result = [];

                    $selectedAnswers = array_flip($data['answer_uuid'] ?? []);

                    if ($get_answers->count() == 0) {
                $is_true = 0;
            }

                    foreach ($get_answers as $answer) {

                        $is_selected = isset($selectedAnswers[$answer->uuid]) ? 1 : 0;

                        if ($answer->is_correct == 1 && $is_selected == 0) {
                            $is_true = 0;
                }

                if ($get_question->different_point == 0) {

                            $answers_result[] = [
                                'answer_uuid' => $answer->uuid,
                                'is_correct' => $answer->is_correct,
                        'is_selected' => $is_selected,
                    ];
                } else {

                    if ($is_selected == 1) {
                                $points += abs($answer->point);
                    }

                            $answers_result[] = [
                                'answer_uuid' => $answer->uuid,
                        'is_correct' => 1,
                        'is_selected' => $is_selected,
                    ];
                }
                    }

                    if ($get_question->different_point == 0 && $is_true == 1) {
                    $points += $get_question->point;
                    $tskkwk_points += 1 * 1.667;
                    $potensiPoints += 1;
            }

            $data_question[] = [
                "question_uuid" => $data['question_uuid'],
                        "answers" => $answers_result,
            ];

                    $progress = 10 + intval(($current / max($total, 1)) * 50);

                    // $this->pushProgress($channel, ProgressStatus::PROCESSING_QUESTIONS, $progress, $user_session->uuid);
        }

                // $this->pushProgress($channel, ProgressStatus::CALCULATING_SCORE, 65, $user_session->uuid);

                $score = 0;

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

                    // $this->pushProgress($channel, ProgressStatus::PREPARING_TRYOUT, 70, $user_session->uuid);

            $check_tryout_segment_test = TryoutSegmentTest::where([
                'uuid' => $user_session->package_test_uuid
            ])->first();

                    if (!$check_tryout_segment_test) {
                return response()->json([
                    'message' => 'Sub tryout tidak ditemukan',
                ]);
            }

            $check_tryout_segment = TryoutSegment::where([
                'uuid' => $check_tryout_segment_test->tryout_segment_uuid,
            ])->first();

            $check_tryout = Tryout::where([
                'uuid' => $check_tryout_segment->tryout_uuid,
            ])->first();

            $get_package_test = PackageTest::where([
                'test_uuid' => $check_tryout->uuid,
            ])->first();

            $get_package = Package::where([
                'uuid' => $get_package_test->package_uuid
            ])->first();

                    $count = StudentTryout::where([
                        'user_uuid' => $user_session->user_uuid,
                        'package_test_uuid' => $user_session->package_test_uuid,
                    ])->count();

                    if ($test->test_type == 'TSKKWK') {

                        $score = $tskkwk_points;
                    } elseif ($test->test_type == 'Tes Potensi') {

                        $score = round(($potensiPoints / 40) * 200);
                    } elseif ($test->test_type == 'IRT') {

                        // $this->pushProgress($channel, ProgressStatus::SAVING_ATTEMPT, 75, $user_session->uuid);

                        $package_test_uuid = $user_session->package_test_uuid;

                    $student_tryout = StudentTryout::create([
                        'data_question' => json_encode($data_question),
                        'user_uuid' => $user_session->user_uuid,
                        'package_uuid' => $get_package->uuid,
                            'package_test_uuid' => $package_test_uuid,
                        'attempt' => $count + 1,
                        'score' => $points,
                    ]);

                        $score = $points;

                        // $this->pushProgress($channel, ProgressStatus::PROCESSING_IRT, 80, $user_session->uuid);

                        $check_irt_point = IrtPoint::where([
                            'package_test_uuid' => $package_test_uuid
                        ])->first();

                        if ($check_irt_point) {

                    $this->RecalculatePoint($check_irt_point, [$student_tryout]);

                    $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
                                $query->select(DB::raw('MAX(id)'))
                            ->from('student_tryouts')
                            ->where('package_test_uuid', $package_test_uuid)
                            ->groupBy('user_uuid');
                            })->get();

                    $allStudentAttempts = StudentTryout::where([
                        'package_test_uuid' => $package_test_uuid,
                    ])->get();

                    foreach ($total_submit_IRT as $total_submiter) {

                        if ($total_submiter > $check_irt_point->total_submit) {
                            if (count($latestAttempts) >= $total_submiter) {

                                        // $this->pushProgress($channel, ProgressStatus::RECALCULATING_IRT, 90, $user_session->uuid);

                                $this->calculateIRT($package_test_uuid, $user_session);

                                $this->RecalculatePoint($check_irt_point, $allStudentAttempts);
                            }
                        }
                    }
                } else {

                    $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
                                $query->select(DB::raw('MAX(id)'))
                            ->from('student_tryouts')
                            ->where('package_test_uuid', $package_test_uuid)
                            ->groupBy('user_uuid');
                            })->get();

                    if (count($latestAttempts) > $total_submit_IRT[0]) {

                                // $this->pushProgress($channel, ProgressStatus::INITIALIZING_IRT, 90, $user_session->uuid);

                        $this->calculateIRT($package_test_uuid, $user_session);

                        $allStudentAttempts = StudentTryout::where([
                            'package_test_uuid' => $package_test_uuid,
                        ])->get();

                        $check_irt_point = IrtPoint::where([
                            'package_test_uuid' => $package_test_uuid
                        ])->first();

                        $this->RecalculatePoint($check_irt_point, $allStudentAttempts);
                    }
                        }
                    } else {

                        $score = $points;
                    }

                    if ($test->test_type != 'IRT') {
                    StudentTryout::create([
                        'data_question' => json_encode($data_question),
                        'user_uuid' => $user_session->user_uuid,
                        'package_uuid' => $get_package->uuid,
                        'package_test_uuid' => $user_session->package_test_uuid,
                        'attempt' => $count + 1,
                            'score' => $score,
                    ]);
                }
                }

                // $this->pushProgress($channel, ProgressStatus::DONE, 100, $user_session->uuid);

        SessionTest::where(['uuid' => $session_uuid])->delete();

        return response()->json([
            'message' => 'Test berhasil dikirim',
            'score' => $score
        ], 200);
            });

        } catch (\Throwable $e) {

            Log::error('Submit Test Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan di server'
            ], 500);
        }
    }

    public function RecalculatePoint($check_irt_point, $student_tryout)
    {
        $check_irt_point = json_decode($check_irt_point->data_question);
        foreach ($student_tryout as $index => $tryout) {
            $points = 0;
            $data_question = json_decode($tryout->data_question);
            foreach ($data_question as $key => $question) {
                foreach ($question->answers as $key1 => $answer) {
                    if ($answer->is_selected == 1 && $answer->is_correct == 1) {
                        $points += $check_irt_point[$key];
                    }
                }
            }

            StudentTryout::where([
                'uuid' => $tryout->uuid,
            ])->update([
                'score' => $points
            ]);
        }
    }

    public function calculateIRT($package_test_uuid, $user_session)
    {
        $get_package_test = TryoutSegmentTest::where('uuid', $package_test_uuid)->first();
        $get_package = Package::where('uuid', $get_package_test->package_uuid)->first();
        $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
            $query->select(\DB::raw('MAX(id)'))
                ->from('student_tryouts')
                ->where('package_test_uuid', $package_test_uuid)
                ->groupBy('user_uuid');
        })
            ->get();

        $wrong_answers = [];
        $zero_student = false;

        foreach ($latestAttempts as $index => $test) {
            $data_question = json_decode($test->data_question);
            $i = 0;
            foreach ($data_question as $index2 => $data) {
                $i++;
                if (!isset($wrong_answers[$index2])) {
                    $wrong_answers[$index2] = 0;
                }
                $no_anwer = true;

                foreach ($data->answers as $index1 => $answer) {
                    if ($answer->is_selected == 1 && $answer->is_correct == 0) {
                        $no_anwer = false;
                        $wrong_answers[$index2] += 1;
                    }
                }

                if ($no_anwer) {
                    $wrong_answers[$index2] += 1;
                }

                if ($wrong_answers[$index2] <= 0) {
                    $zero_student = true;
                }
            }
        }

        // Inisialisasi total point keseluruhan
        $total_point = $get_package_test->max_point;

        // Faktor skala untuk menentukan seberapa besar pengaruh jumlah yang salah terhadap point
        $scale_factor = 1;

        // Hitung total point per soal
        $point_per_soal = [];

        foreach ($wrong_answers as $soal => $jumlah_salah) {
            $jumlah = $jumlah_salah;
            if ($zero_student) {
                $jumlah = $jumlah_salah + 1;
            }
            // Hitung point per soal berdasarkan jumlah yang salah
            $point = $total_point * ($jumlah / array_sum($wrong_answers)) * $scale_factor;

            // Simpan point per soal
            $point_per_soal[$soal] = intval($point);
        }

        // Hitung total point keseluruhan
        $total_point_after_adjustment = array_sum($point_per_soal);

        // Periksa jika total point setelah penyesuaian lebih kecil dari total point awal
        if ($total_point_after_adjustment < $total_point) {
            // Temukan soal dengan nilai tertinggi
            $soal_tertinggi = array_search(max($point_per_soal), $point_per_soal);

            // Tambahkan selisih ke soal tertinggi
            $selisih = $total_point - $total_point_after_adjustment;
            $point_per_soal[$soal_tertinggi] += $selisih;

            // Hitung total point keseluruhan setelah penyesuaian ulang
            $total_point_after_adjustment = array_sum($point_per_soal);
        }

        $check_irt_point = IrtPoint::where([
            'package_test_uuid' => $user_session->package_test_uuid
        ])->first();

        if ($check_irt_point == null) {
            IrtPoint::create([
                'total_submit' => count($latestAttempts),
                'data_question' => json_encode($point_per_soal),
                'package_test_uuid' => $user_session->package_test_uuid
            ]);
        } else {
            IrtPoint::where([
                'package_test_uuid' => $user_session->package_test_uuid
            ])->update([
                'total_submit' => count($latestAttempts),
                'data_question' => json_encode($point_per_soal),
            ]);
        }
    }
}
