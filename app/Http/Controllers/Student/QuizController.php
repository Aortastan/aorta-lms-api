<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\LessonQuiz;
use App\Models\CourseLesson;
use App\Models\Course;
use App\Models\PackageCourse;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\StudentQuiz;
use App\Models\Test;
use App\Models\SessionTest;
use App\Models\QuestionTest;
use App\Models\Question;
use App\Models\Answer;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    public function index($quiz_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getQuiz = LessonQuiz::
                select('uuid', 'title', 'lesson_uuid', 'description', 'duration', 'max_attempt')
                ->where(['uuid' => $quiz_uuid])
                ->first();

            if(!$getQuiz){
                return response()->json([
                    'message' => "Kuis tidak ditemukan",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $getQuiz->lesson_uuid])
                ->first();

            $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getLesson->course_uuid);

            if($checkCourseIsPurchasedOrMembership != null){
                return $checkCourseIsPurchasedOrMembership;
            }


            $quizzes = StudentQuiz::
            select('uuid', 'score')
            ->where([
                'user_uuid' => $user->uuid,
                'lesson_quiz_uuid' => $getQuiz->uuid,
            ])->get();

            $getQuiz['student_attempts'] = $quizzes;

            return response()->json([
                'message' => 'Success get data',
                'quiz' => $getQuiz,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show(Request $request, $student_quiz_uuid){
        $user = JWTAuth::parseToken()->authenticate();
        $student_quiz = StudentQuiz::
        select('uuid', 'data_question', 'score', 'lesson_quiz_uuid')
        ->where([
            'user_uuid' => $user->uuid,
            'uuid' => $student_quiz_uuid,
        ])->first();

        if(!$student_quiz){
            return response()->json([
                'message' => "Student Quiz not found",
            ], 404);
        }

        $getQuiz = LessonQuiz::
            select('uuid', 'title', 'lesson_uuid', 'description', 'duration', 'max_attempt')
            ->where(['uuid' => $student_quiz->lesson_quiz_uuid])
            ->first();

        if(!$getQuiz){
            return response()->json([
                'message' => "Quiz not found",
            ], 404);
        }

        $getLesson = CourseLesson::
            where(['uuid' => $getQuiz->lesson_uuid])
            ->first();

        $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getLesson->course_uuid);

        if($checkCourseIsPurchasedOrMembership != null){
            return $checkCourseIsPurchasedOrMembership;
        }

        $data_question = json_decode($student_quiz->data_question);

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
            'message' => 'Sukses mengambil data',
            'score' => $student_quiz->score,
            'questions' => $questions
        ], 200);
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
                'message' => "Paket kursus tidak ditemukan",
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
                    'message' => 'Kamu tidak dapat mengakses paket ini',
                ]);
            }
        }

        return null;
    }

    public function submit(Request $request, $package_uuid, $quiz_uuid){
        try{
            $validate = [
                'student_answers' => 'required|array',
                'student_answers.*.answer_uuid' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validate);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $user = JWTAuth::parseToken()->authenticate();
            $getPackage = Package::
                where(['uuid' => $package_uuid])
                ->first();

            if(!$getPackage){
                return response()->json([
                    'message' => "Paket tidak ditemukan",
                ], 404);
            }

            $getQuiz = LessonQuiz::
                select('uuid', 'title', 'description', 'duration', 'max_attempt')
                ->where(['uuid' => $quiz_uuid])
                ->first();

            if(!$getQuiz){
                return response()->json([
                    'message' => "Tugas tidak ditemukan",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $getQuiz->lesson_uuid])
                ->first();

            $getCourse = Course::
                where(['uuid' => $getLesson->course_uuid])
                ->first();

            $getPackageCourse = PackageCourse::where([
                'package_uuid' => $getPackage->uuid,
                'course_uuid' => $getCourse->uuid,
            ])->first();

            if(!$getAssignment){
                return response()->json([
                    'message' => "Paket atau tugas tidak valid",
                ], 404);
            }

            $check_purchased_package = DB::table('purchased_packages')
                ->where(['purchased_packages.user_uuid' => $user->uuid, 'purchased_packages.package_uuid' => $getPackage->uuid])
                ->first();

            if(!$check_purchased_package){
                $check_membership_history = DB::table('membership_histories')
                    ->where(['membership_histories.user_uuid' => $user->uuid, 'membership_histories.package_uuid' => $getPackage->uuid])
                    ->whereDate('membership_histories.expired_date', '>', now())
                    ->first();

                if(!$check_membership_history){
                    return response()->json([
                        'message' => "You haven't purchased this package yet",
                    ], 404);
                }
            }

            $getTest = Test::where([
                'uuid' => $getQuiz->test_uuid,
            ])->first();

            $getQuestions = QuestionTest::where([
                'test_uuid' => $getTest->uuid,
            ])->with(['question'])->get();

            if(count($getQuestions) != count($request->student_answers)){
                return response()->json([
                    'message' => 'Data tidak lengkap',
                ], 422);
            }
            $score = 0;
            $data_question = [];
            foreach ($getQuestions as $index => $question) {
                dd($index);
                foreach ($question->question->answers as $index1 => $answer) {
                    if($answer->uuid == $request->student_answers[$index]){
                        $data_question[] = [
                            "question" => $question->question->question,
                            "answer_uuid" => $answer->uuid,
                            "answer" => $answer->answer,
                            "is_correct" => $answer->is_correct,
                            "point" => $answer->point,
                        ];
                        $score += $answer->point;
                    }
                }
            }


            StudentQuiz::
            create([
                'data_question' => json_encode($data_question),
                'user_uuid' => $user->uuid,
                'lesson_quiz_uuid' => $getQuiz->uuid,
                'score' => $score,
            ]);
            return response()->json([
                'message' => 'Sukses mengirim data',
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function takeQuiz($quiz_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
        $getQuiz = LessonQuiz::
            select('uuid', 'title', 'lesson_uuid', 'test_uuid', 'description', 'duration', 'max_attempt')
            ->where(['uuid' => $quiz_uuid])
            ->first();

        if(!$getQuiz){
            return response()->json([
                'message' => "Kuis tidak ditemukan",
            ], 404);
        }

        $getLesson = CourseLesson::
            where(['uuid' => $getQuiz->lesson_uuid])
            ->first();

        // cek apakah course tersebut sudah pernah dibeli atau belum
        $checkCourseIsPurchasedOrMembership = $this->checkCourseIsPurchasedOrMembership($user, $getLesson->course_uuid);
        if($checkCourseIsPurchasedOrMembership != null){
            return $checkCourseIsPurchasedOrMembership;
        }

        // cek apakah quiz sudah melewati max attempt
        $checkQuizMaxAttempt = $this->checkQuizMaxAttempt($user, $getQuiz);
        if($checkQuizMaxAttempt != null){
            return $checkQuizMaxAttempt;
        }

        // cek session
        $sessionTest = $this->checkQuizSession($user, $getQuiz);

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

        $quiz = [
            'session_uuid' => $sessionTest->uuid,
            'duration_left' => $sessionTest->duration_left,
            'questions' => $questions,
        ];

        return response()->json([
            'message' => "Sukses mengambil data",
            'question' => $quiz,
        ], 200);
        }catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function checkQuizMaxAttempt($user, $quiz){
        $studentQuiz = StudentQuiz::where([
            'user_uuid' => $user->uuid,
            'lesson_quiz_uuid' => $quiz->uuid,
        ])->count();

        if($studentQuiz >= $quiz->max_attempt){
            return response()->json([
                'success' => false,
                'message' => "Kamu telah memenuhi maksimum percobaan",
            ]);
        }
        return null;
    }

    public function checkQuizSession($user, $quiz){
        $sessionTest = SessionTest::where([
            'user_uuid' => $user->uuid,
            'lesson_quiz_uuid' => $quiz->uuid,
            'type_test' => 'quiz',
        ])->first();

        if($sessionTest == null){
            $sessionTest = $this->createQuizSession($user, $quiz);
        }

        return $sessionTest;
    }
    public function createQuizSession($user, $quiz){
        try{
            $data_question = [];
        $get_test = Test::where([
            'uuid' => $quiz->test_uuid
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
            'duration_left' => $quiz->duration,
            'lesson_quiz_uuid' => $quiz->uuid,
            'type_test' => 'quiz',
            'test_uuid' => $quiz->test_uuid,
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
