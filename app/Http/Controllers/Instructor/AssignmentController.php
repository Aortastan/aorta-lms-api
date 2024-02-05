<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\StudentAssignment;
use Tymon\JWTAuth\Facades\JWTAuth;

class AssignmentController extends Controller
{
    public function index($assignment_uuid){
        $user = JWTAuth::parseToken()->authenticate();

        $get_assignment = Assignment::where([
            'uuid' => $assignment_uuid,
        ])->with(['AssignmentWaitingForReview', 'AssignmentWaitingForReview.student'])->first();

        if($get_assignment == null){
            return response()->json([
                'message' => 'Assignment not found'
            ], 404);
        }

        $getLesson = CourseLesson::
            where(['uuid' => $get_assignment->lesson_uuid])
            ->first();

        // cek apakah course uuid tersebut ada
        $course = Course::where([
            'uuid' => $getLesson->course_uuid,
        ])->first();

        if($course->instructor_uuid != $user->uuid){
            return response()->json([
                'message' => 'You\'re not allowed'
            ], 400);
        }

        $student_assignments = [];
        foreach ($get_assignment->AssignmentWaitingForReview as $index => $assignment_student) {
            $student_assignments[] = [
                'student_assignment_uuid' => $assignment_student->uuid,
                'student_name' => $assignment_student->student->name,
                'assignment_url' => $assignment_student->assignment_url,
                'status' => $assignment_student->status,
            ];
        }

        $assignment = [
            'assignment_uuid' => $get_assignment->uuid,
            'title' => $get_assignment->title,
            'description' => $get_assignment->description,
            'student_assignments' => $student_assignments,
        ];

        return response()->json([
            'message' => 'Success get data',
            'assignment' => $assignment,
        ], 200);
    }

    public function review(Request $request, $student_assignment_uuid){
        $user = JWTAuth::parseToken()->authenticate();

        $validate = [
            'feedback' => 'required|string',
            'status' => 'required|string|in:Revise,Done'
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $get_student_assignment = StudentAssignment::where([
            'uuid' => $student_assignment_uuid,
            'status' => 'Waiting for review',
        ])->first();

        if($get_student_assignment == null){
            return response()->json([
                'message' => 'Student assignment not found',
            ], 404);
        }

        $get_assignment = Assignment::where([
            'uuid' => $get_student_assignment->assignment_uuid,
        ])->first();

        if($get_assignment == null){
            return response()->json([
                'message' => 'Assignment not found'
            ], 404);
        }

        $getLesson = CourseLesson::
            where(['uuid' => $get_assignment->lesson_uuid])
            ->first();

        // cek apakah course uuid tersebut ada
        $course = Course::where([
            'uuid' => $getLesson->course_uuid,
        ])->first();

        if($course->instructor_uuid != $user->uuid){
            return response()->json([
                'message' => 'You\'re not allowed'
            ], 400);
        }


        StudentAssignment::where([
            'uuid' => $student_assignment_uuid,
        ])->update([
            'feedback' => $request->feedback,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }
}
