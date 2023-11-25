<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use App\Models\User;
use App\Models\Test;
use App\Models\LessonQuiz;
use App\Models\Assignment;
use App\Models\CourseLesson;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

class CourseLessonController extends Controller
{
    public function show(Request $request, $uuid){
        try{
            $checkLesson = CourseLesson::where(['uuid' => $uuid])->with(['assignments', 'quizzes', 'lectures'])->first();

            $lesson = [];
            if($checkLesson){
                $lesson = [
                    "uuid" => $checkLesson->uuid,
                    "course_uuid" => $checkLesson->course_uuid,
                    "title" => $checkLesson->title,
                    "description" => $checkLesson->description,
                    "is_have_quiz" => $checkLesson->is_have_quiz,
                    "is_have_assignment" => $checkLesson->is_have_assignment,
                    "assignments" => [],
                    "quizzes" => [],
                    "lectures" => [],
                ];

                foreach ($checkLesson->assignments as $index => $assignment) {
                    $lesson['assignments'][] = [
                        "uuid" => $assignment['uuid'],
                        "description" => $assignment['description'],
                        "title" => $assignment['title']
                    ];
                }

                foreach ($checkLesson->quizzes as $index => $quiz) {
                    $lesson['quizzes'][] = [
                        "uuid" => $quiz['uuid'],
                        "test_uuid" => $quiz['test_uuid'],
                        "description" => $quiz['description'],
                        "duration" => $quiz['duration'],
                        "max_attempt" => $quiz['max_attempt'],
                        "title" => $quiz['title']
                    ];
                }

                foreach ($checkLesson->lectures as $index => $lecture) {
                    $lesson['lectures'][] = [
                        "uuid" => $lecture['uuid'],
                        "title" => $lecture['title'],
                        "type" => $lecture['type'],
                    ];
                }
            }

            return response()->json([
                'message' => 'Success get data',
                'lesson' => $lesson,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse{
        $checkCourse = Course::where(['uuid' => $request->course_uuid])->first();
        if(!$checkCourse){
            return response()->json([
                'message' => 'Course not found',
            ], 404);
        }
        $validate = [
            'course_uuid' => 'required|string',
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
            'course_uuid' => $request->course_uuid,
            'title' => $request->title,
        ];

        $lesson = CourseLesson::create($validated);

        return response()->json([
            'message' => 'Success create new lesson',
            'lesson' => [
                'lesson_uuid' => $lesson->uuid,
                'title' => $lesson->title,
            ],
        ], 200);

    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkLesson = CourseLesson::where(['uuid' => $uuid])->first();
        if(!$checkLesson){
            return response()->json([
                'message' => 'Lesson not found',
            ], 404);
        }

        $validate = [
            'title' => 'required|string',
            'is_have_quiz' => 'required|boolean',
            'is_have_assignment' => 'required|boolean',
        ];

        if($request->is_have_quiz == 1){
            $validate['quizzes'] = 'required|array';
            $validate['quizzes.*.uuid'] = 'required';
            $validate['quizzes.*.title'] = 'required|string';
            $validate['quizzes.*.description'] = 'required|string';
            $validate['quizzes.*.max_attempt'] = 'required|numeric';
            $validate['quizzes.*.duration'] = 'required|numeric';
            $validate['quizzes.*.test_uuid'] = 'required|string';
        }

        if($request->is_have_assignment == 1){
            $validate['assignments'] = 'required|array';
            $validate['assignments.*.title'] = 'required';
            $validate['assignments.*.uuid'] = 'required';
            $validate['assignments.*.description'] = 'required';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'title' => $request->title,
            'description' => $request->description,
            'is_have_quiz' => $request->is_have_quiz,
            'is_have_assignment' => $request->is_have_assignment,
        ];

        CourseLesson::where(['uuid' => $uuid])->update($validated);

        $validated_new_quizzes = [];
        $validated_new_assignments = [];
        $assignment_uuid =[];
        $quiz_uuid =[];
        if($request->is_have_quiz == 1){
            foreach ($request->quizzes as $index => $quiz) {
                $checkTest = Test::where('uuid', $quiz['test_uuid'])->first();
                if(!$checkTest){
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => "Test not found",
                    ], 404);
                }

                $checkquiz = LessonQuiz::where(['uuid' => $quiz['uuid']])->first();

                if(!$checkquiz){
                    $validated_new_quizzes[] = [
                        'uuid' => Uuid::uuid4()->toString(),
                        'lesson_uuid' => $uuid,
                        'test_uuid' => $quiz['test_uuid'],
                        'title' => $quiz['title'],
                        'description' => $quiz['description'],
                        'duration' => $quiz['duration'],
                        'max_attempt' => $quiz['max_attempt'],
                        'status' => 1,
                    ];
                }else{
                    $quiz_uuid[] = $checkquiz->uuid;
                    $validate = [
                        'test_uuid' => $quiz['test_uuid'],
                        'title' => $quiz['title'],
                        'description' => $quiz['description'],
                        'duration' => $quiz['duration'],
                        'max_attempt' => $quiz['max_attempt'],
                    ];

                    LessonQuiz::where(['uuid' => $checkquiz->uuid])->update($validate);
                }

            }
            LessonQuiz::where(['lesson_uuid' => $checkLesson->uuid])->whereNotIn('uuid', $quiz_uuid)->delete();
            if(count($validated_new_quizzes) > 0){
                LessonQuiz::insert($validated_new_quizzes);
            }

        }
        if($request->is_have_assignment == 1){
            foreach ($request->assignments as $index => $assignment) {
                $checkAssignment = Assignment::where(['uuid' => $assignment['uuid']])->first();

                if(!$checkAssignment){
                    $validated_new_assignments[] = [
                        'uuid' => Uuid::uuid4()->toString(),
                        'lesson_uuid' => $uuid,
                        'title' => $assignment['title'],
                        'description' => $assignment['description'],
                        'status'=> 1,
                    ];
                }else{
                    $assignment_uuid[] = $assignment['uuid'];
                    $validate = [
                        'title' => $assignment['title'],
                        'description' => $assignment['description'],
                    ];

                    Assignment::where(['uuid' => $assignment['uuid']])->update($validate);
                }

            }
            Assignment::where(['lesson_uuid' => $checkLesson->uuid])->whereNotIn('uuid', $assignment_uuid)->delete();
            if(count($validated_new_assignments) > 0){
                Assignment::insert($validated_new_assignments);
            }
        }

        return response()->json([
            'message' => 'Success update lesson'
        ], 200);

    }

}
