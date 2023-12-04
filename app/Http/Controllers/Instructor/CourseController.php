<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use App\Models\Course;

class CourseController extends Controller
{
    public function index(){
        $user = JWTAuth::parseToken()->authenticate();
        $courses = Course::where([
            'instructor_uuid' => $user->uuid,
        ])->get();

        return response()->json([
            'message' => 'Success get data',
            'courses' => $courses,
        ], 200);
    }

    public function show(Request $request, $uuid){
        $user = JWTAuth::parseToken()->authenticate();
        $getCourse = Course::where([
            'uuid' => $uuid,
            'instructor_uuid' => $user->uuid,
        ])->with(['instructor', 'lessons', 'pretestPosttests', 'pretestPosttests.test', 'lessons.lectures', 'lessons.quizzes', 'lessons.assignments'])->first();

        if($getCourse == null){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }


        $pretest_posttests = [];
        foreach ($getCourse->pretestPosttests as $index => $test) {
            $pretest_posttests[] = [
                "pretest_posttest_uuid" => $test->uuid,
                "title" => $test->test->title,
                "test_uuid" => $test->test->uuid,
                "test_category" => $test->test->test_category,
                "max_attempt" => $test->max_attempt,
            ];
        }

        $lessons = [];
        foreach ($getCourse->lessons as $index => $lesson) {
            $lesson_lectures = [];
            foreach ($lesson->lectures as $index1 => $lecture_data) {
                $lesson_lectures[] = [
                    "lecture_uuid" => $lecture_data->uuid,
                    "title" => $lecture_data->title,
                ];
            }

            $quizzes = [];
            foreach ($lesson->quizzes as $index1 => $quiz) {
                $quizzes[] = [
                    "quiz_uuid" => $quiz->uuid,
                    "test_uuid" => $quiz->test_uuid,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'duration' => $quiz->duration,
                    'max_attempt' => $quiz->max_attempt,
                ];
            }

            $assignments = [];
            foreach ($lesson->assignments as $index1 => $assignment) {
                $assignments[] = [
                    "assignment_uuid" => $assignment->uuid,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                ];
            }


            $lessons[] = [
                "lesson_uuid" => $lesson->uuid,
                "title" => $lesson->title,
                "description" => $lesson->description,
                "lectures" => $lesson_lectures,
                "quizzes" => $quizzes,
                "assignments" => $assignments,
            ];
        }
        $course = [
            "uuid" => $getCourse->uuid,
            "title" => $getCourse->title,
            "instructor_name" => $getCourse->instructor->name,
            'description' => $getCourse->description,
            "image" => $getCourse->image,
            "video" => $getCourse->video,
            'number_of_meeting' => $getCourse->number_of_meeting,
            "lessons" =>$lessons,
            "pretest_posttests" => $pretest_posttests,
        ];

        return response()->json([
            'message' => 'Success get data',
            "course" =>$course
        ], 200);
    }
}
