<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SessionTest;
use App\Models\StudentQuiz;
use App\Models\StudentPretestPosttest;
use App\Models\StudentTryout;
use App\Models\Question;
use App\Models\Package;
use App\Models\PackageTest;
use App\Models\IrtPoint;
use Illuminate\Support\Facades\Validator;

class SubmitTestController extends Controller
{
    public function submitTest(Request $request, $session_uuid){
        $total_submit_IRT = [1, 3, 50];
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

        foreach ($request->data_question as $index => $data) {
            $get_question = Question::where([
                'uuid' => $data['question_uuid'],
            ])->with(['answers'])->first();

            $is_true = 0;

            $answers = [];
            $is_true = 1; // Assume all answers are correct by default

            foreach ($get_question->answers as $index1 => $answer) {
                $is_selected = in_array($answer['uuid'], $data['answer_uuid']) ? 1 : 0;

                if ($answer['is_correct'] == 1 && $is_selected == 0) {
                    $is_true = 0; // Set to false if any correct answer is not selected
                }

                if ($get_question->different_point == 0) {
                    $answers[] = [
                        'answer_uuid' => $answer['uuid'],
                        'is_correct' => $answer['is_correct'],
                        'is_selected' => $is_selected,
                    ];
                } else {
                    if ($answer['is_correct'] == 1) {
                        $points += $answer['point'];
                    } elseif ($is_selected == 1) {
                        // Only subtract points for incorrect selected answers
                        $points -= abs($answer['point']);
                    }
                }

                // // Debugging statements
                // echo "Answer: " . $answer['uuid'] . ", Is Correct: " . $answer['is_correct'] . ", Is Selected: " . $is_selected . ", Points: " . $points . "\n";
            }

            // Debugging statement
            // echo "Is True: " . $is_true . "\n";

            if ($get_question->different_point == 0) {
                if ($is_true == 1) {
                    $points += $get_question->point;
                }
            }

            $data_question[] = [
                "question_uuid" => $data['question_uuid'],
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
            $get_package_test = PackageTest::where([
                'uuid' => $user_session->package_test_uuid
            ])->first();

            $get_package = Package::where([
                'uuid' => $get_package_test->package_uuid
            ])->first();
            if($get_package->test_type == 'IRT'){
                // cek apakah sudah ada di IRTpoint
                $check_irt_point = IrtPoint::where([
                    'package_test_uuid' => $user_session->package_test_uuid
                ])->first();

                if($check_irt_point){
                    $student_tryout = StudentTryout::create([
                        'data_question' => json_encode($data_question),
                        'user_uuid' => $user_session->user_uuid,
                        'package_test_uuid' => $user_session->package_test_uuid,
                        'score' => $points,
                    ]);

                    $this->RecalculatePoint($check_irt_point, [$student_tryout]);

                    $package_test_uuid = $user_session->package_test_uuid;
                    $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
                        $query->select(\DB::raw('MAX(id)'))
                            ->from('student_tryouts')
                            ->where('package_test_uuid', $package_test_uuid)
                            ->groupBy('user_uuid');
                    })
                    ->get();
                    foreach ($total_submit_IRT as $total_submit) {
                        if($total_submit > $check_irt_point->total_submit){
                            if(count($latestAttempts) > $total_submit){
                                $this->calculateIRT($package_test_uuid, $user_session);
                                $this->RecalculatePoint($check_irt_point, $latestAttempts);
                            }
                        }
                    }


                }else{
                    $package_test_uuid = $user_session->package_test_uuid;
                    $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
                        $query->select(\DB::raw('MAX(id)'))
                            ->from('student_tryouts')
                            ->where('package_test_uuid', $package_test_uuid)
                            ->groupBy('user_uuid');
                    })
                    ->get();

                    if(count($latestAttempts) > $total_submit_IRT[0]){
                        $this->calculateIRT($package_test_uuid, $user_session);
                    }

                    StudentTryout::create([
                        'data_question' => json_encode($data_question),
                        'user_uuid' => $user_session->user_uuid,
                        'package_test_uuid' => $user_session->package_test_uuid,
                        'score' => $points,
                    ]);
                }
            }else{
                StudentTryout::create([
                    'data_question' => json_encode($data_question),
                    'user_uuid' => $user_session->user_uuid,
                    'package_test_uuid' => $user_session->package_test_uuid,
                    'score' => $points,
                ]);

            }

        }

        SessionTest::where(['uuid' => $session_uuid])->delete();

        return response()->json([
            'message'=>'Test submitted',
            'score' => $points,
        ], 200);
    }

    public function RecalculatePoint($check_irt_point, $student_tryout){
        $check_irt_point = json_decode($check_irt_point->data_question);
        foreach ($student_tryout as $index => $tryout) {
            $points = 0;
            $data_question = json_decode($tryout->data_question);
            foreach ($data_question as $key => $question) {
                foreach ($question->answers as $key1 => $answer) {
                    if($answer->is_selected == 1 && $answer->is_correct == 1){
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

    public function calculateIRT($package_test_uuid, $user_session){
        $get_package_test = PackageTest::where('uuid', $package_test_uuid)->first();
        $get_package = Package::where('uuid', $get_package_test->package_uuid)->first();
        $latestAttempts = StudentTryout::whereIn('id', function ($query) use ($package_test_uuid) {
            $query->select(\DB::raw('MAX(id)'))
                ->from('student_tryouts')
                ->where('package_test_uuid', $package_test_uuid)
                ->groupBy('user_uuid');
        })
        ->get();

        $wrong_answers = [];

        foreach ($latestAttempts as $index => $test) {
            $data_question = json_decode($test->data_question);
            $i = 0;
            foreach ($data_question as $index2 => $data) {
                 $i++;
                if(!isset($wrong_answers[$index2])){
                    $wrong_answers[$index2] = 0;
                }
                $no_anwer = true;

                foreach ($data->answers as $index1 => $answer) {
                    if($answer->is_selected == 1 && $answer->is_correct == 0){
                        $no_anwer = false;
                        $wrong_answers[$index2] += 1;
                    }
                }

                if($no_anwer){
                    $wrong_answers[$index2] += 1;
                }
            }
        }

        // Inisialisasi total point keseluruhan
        $total_point = $get_package->max_point;

        // Faktor skala untuk menentukan seberapa besar pengaruh jumlah yang salah terhadap point
        $scale_factor = 1;

        // Hitung total point per soal
        $point_per_soal = [];

        foreach ($wrong_answers as $soal => $jumlah_salah) {
            // Hitung point per soal berdasarkan jumlah yang salah
            $point = $total_point * ($jumlah_salah / array_sum($wrong_answers)) * $scale_factor;

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

        if($check_irt_point == null){
            IrtPoint::create([
                'total_submit' => count($latestAttempts),
                'data_question' => json_encode($point_per_soal),
                'package_test_uuid' => $user_session->package_test_uuid
            ]);
        }else{
            IrtPoint::where([
                'package_test_uuid' => $user_session->package_test_uuid
            ])->update([
                'total_submit' => count($latestAttempts),
                'data_question' => json_encode($point_per_soal),
            ]);
        }
    }
}
