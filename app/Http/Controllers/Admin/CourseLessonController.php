<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use App\Models\User;
use App\Models\Test;
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
            $lessons = CourseLesson::where(['uuid' => $uuid])->with(['assignments', 'quizzes', 'lectures'])->first();

            return response()->json([
                'message' => 'Success get data',
                'lessons' => $lessons,
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
            'course_uuid' => 'required',
            'name' => 'required',
            'is_have_quiz' => 'required',
            'is_have_assignment' => 'required',
        ];

        if($request->is_have_quiz == 1){
            $validate['quizzes'] = 'required|array';
            $validate['quizzes.*.name'] = 'required';
            $validate['quizzes.*.description'] = 'required';
            $validate['quizzes.*.max_attempt'] = 'required|numeric';
            $validate['assignments.*.test_uuid'] = 'required';
        }

        if($request->is_have_assignment == 1){
            $validate['assignments'] = 'required|array';
            $validate['assignments.*.name'] = 'required';
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
            'course_uuid' => $request->course_uuid,
            'name' => $request->name,
            'description' => $request->description,
            'is_have_quiz' => $request->is_have_quiz,
            'is_have_assignment' => $request->is_have_assignment,
            'status' => 1
        ];

        $lesson = CourseLesson::create($validated);

        if($request->is_have_quiz == 1){
            $validated_quizzes = [];
            foreach ($request->quizzes as $index => $quiz) {
                $checkTest = Test::where('uuid', $quiz['test_uuid'])->first();
                if(!$checkTest){
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => "Test not found",
                    ], 404);
                }
                $validated_quizzes[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'lesson_uuid' => $lesson->uuid,
                    'name' => $quiz['name'],
                    'description' => $quiz['description'],
                    'duration' => $quiz['duration'],
                    'max_attempt' => $quiz['max_attempt'],
                    'status'=> 1,
                ];
            }
            LessonQuiz::insert($validated_quizzes);
        }
        if($request->is_have_assignment == 1){
            $validated_assignments = [];
            $validated_assignments[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'lesson_uuid' => $lesson->uuid,
                'name' => $quiz['name'],
                'description' => $quiz['description'],
                'status'=> 1,
            ];
        }
        Assignment::insert($validated_assignments);
        return response()->json([
            'message' => 'Success create new lesson'
        ], 200);

    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkCourse = Course::where(['uuid' => $request->course_uuid])->first();
        if(!$checkCourse){
            return response()->json([
                'message' => 'Course not found',
            ], 404);
        }
        $checkLesson = CourseLesson::where(['uuid' => $uuid])->first();
        if(!$checkLesson){
            return response()->json([
                'message' => 'Lesson not found',
            ], 404);
        }

        $validate = [
            'course_uuid' => 'required',
            'name' => 'required',
            'is_have_quiz' => 'required',
            'is_have_assignment' => 'required',
            'status' => 'required',
        ];

        if($request->is_have_quiz == 1){
            $validate['quizzes'] = 'required|array';
            $validate['quizzes.*.name'] = 'required';
            $validate['quizzes.*.uuid'] = 'required';
            $validate['quizzes.*.description'] = 'required';
            $validate['quizzes.*.max_attempt'] = 'required|numeric';
            $validate['quizzes.*.test_uuid'] = 'required';
            $validate['quizzes.*.status'] = 'required';
        }

        if($request->is_have_assignment == 1){
            $validate['assignments'] = 'required|array';
            $validate['assignments.*.name'] = 'required';
            $validate['assignments.*.uuid'] = 'required';
            $validate['assignments.*.description'] = 'required';
            $validate['assignments.*.status'] = 'required';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'course_uuid' => $request->course_uuid,
            'name' => $request->name,
            'description' => $request->description,
            'is_have_quiz' => $request->is_have_quiz,
            'is_have_assignment' => $request->is_have_assignment,
            'status' => $request->status,
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
                        'lesson_uuid' => $checkLesson->uuid,
                        'test_uuid' => $quiz['test_uuid'],
                        'name' => $quiz['name'],
                        'description' => $quiz['description'],
                        'duration' => $quiz['duration'],
                        'max_attempt' => $quiz['max_attempt'],
                        'status'=> $quiz['status'],
                    ];
                }else{
                    $quiz_uuid[] = $checkquiz->uuid;
                    $validate = [
                        'test_uuid' => $quiz['test_uuid'],
                        'name' => $quiz['name'],
                        'description' => $quiz['description'],
                        'duration' => $quiz['duration'],
                        'max_attempt' => $quiz['max_attempt'],
                        'status'=> $quiz['status'],
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
                        'lesson_uuid' => $lesson->uuid,
                        'name' => $quiz['name'],
                        'description' => $quiz['description'],
                        'status'=> $quiz['status'],
                    ];
                }else{
                    $assignment_uuid[] = $assignment->uuid;
                    $validate = [
                        'name' => $quiz['name'],
                        'description' => $quiz['description'],
                        'status'=> $quiz['status'],
                    ];

                    Assignment::where(['uuid' => $assignment->uuid])->update($validate);
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
