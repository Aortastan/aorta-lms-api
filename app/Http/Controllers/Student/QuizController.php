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
use App\Models\StudentQuiz;
use App\Models\Test;
use App\Models\QuestionTest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    public function index($package_uuid, $quiz_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getPackage = Package::
                where(['uuid' => $package_uuid])
                ->first();

            if(!$getPackage){
                return response()->json([
                    'message' => "Package not found",
                ], 404);
            }

            $getQuiz = LessonQuiz::
                select('uuid', 'name', 'description', 'duration', 'max_attempt')
                ->where(['uuid' => $quiz_uuid])
                ->first();

            if(!$getQuiz){
                return response()->json([
                    'message' => "Assignment not found",
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
                    'message' => "Package or assignment not valid",
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



            $quizzes = StudentQuiz::
            select('uuid', 'score')
            ->where([
                'user_uuid' => $user->uuid,
                'lesson_quiz_uuid' => $getQuiz->uuid,
            ])->get();

            $getQuiz['student_quizzes'] = $quizzes;

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
                    'message' => "Package not found",
                ], 404);
            }

            $getQuiz = LessonQuiz::
                select('uuid', 'name', 'description', 'duration', 'max_attempt')
                ->where(['uuid' => $quiz_uuid])
                ->first();

            if(!$getQuiz){
                return response()->json([
                    'message' => "Assignment not found",
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
                    'message' => "Package or assignment not valid",
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
                    'message' => 'Incomplete data',
                ], 422);
            }
            $score = 0;
            $data_question = [];
            foreach ($getQuestions as $index => $question) {
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
                'message' => 'Success post data',
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function takeQuiz($package_uuid, $quiz_uuid){
        $user = JWTAuth::parseToken()->authenticate();
            $getPackage = Package::
                where(['uuid' => $package_uuid])
                ->first();

            if(!$getPackage){
                return response()->json([
                    'message' => "Package not found",
                ], 404);
            }

            $getQuiz = LessonQuiz::
                select('uuid', 'name', 'description', 'duration', 'max_attempt')
                ->where(['uuid' => $quiz_uuid])
                ->with(['question', 'question.answer'])
                ->first();

            if(!$getQuiz){
                return response()->json([
                    'message' => "Assignment not found",
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
                    'message' => "Package or assignment not valid",
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

            return response()->json([
                'message' => "Success get data",
                'question' => $getQuiz,
            ], 404);
    }

    public function show($student_quiz_uuid){
        $user = JWTAuth::parseToken()->authenticate();

        $student_quiz = StudentQuiz::where([
            'uuid' => $student_quiz_uuid,
            'user_id' => $user->id,
        ])->with(['question', 'question.answer'])->first();

        return response()->json([
            'message' => "Success get data",
            'quiz' => $student_quiz,
        ], 200);
    }
}
