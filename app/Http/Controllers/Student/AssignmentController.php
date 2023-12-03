<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\Assignment;
use App\Models\CourseLesson;
use App\Models\Course;
use App\Models\PackageCourse;
use App\Models\StudentAssignment;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use Tymon\JWTAuth\Facades\JWTAuth;

class AssignmentController extends Controller
{
    public function index($assignment_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getAssignment = Assignment::
                select('uuid', 'title', 'description', 'lesson_uuid')
                ->where(['uuid' => $assignment_uuid])
                ->first();

            if(!$getAssignment){
                return response()->json([
                    'message' => "Assignment not found",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $getAssignment->lesson_uuid])
                ->first();

            // cek apakah course uuid tersebut ada
            $course = Course::where([
                'uuid' => $getLesson->course_uuid,
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
                    'message' => "Package course not found",
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
                        'message' => 'You can\'t access this course',
                    ]);
                }
            }

            $assignment = StudentAssignment::
            select('uuid', 'assignment_url', 'feedback', 'status')
            ->where([
                'student_uuid' => $user->uuid,
                'assignment_uuid' => $getAssignment->uuid,
            ])->first();

            $getAssignment['student_assignments'] = $assignment;

            return response()->json([
                'message' => 'Success get data',
                'assignment' => $getAssignment,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request, $assignment_uuid){
        try{
            $validate = [
                'assignment_url' => 'required|string',
            ];

            $validator = Validator::make($request->all(), $validate);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $getAssignment = Assignment::
                select('uuid', 'title', 'description', 'lesson_uuid')
                ->where(['uuid' => $assignment_uuid])
                ->first();

            if(!$getAssignment){
                return response()->json([
                    'message' => "Assignment not found",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $getAssignment->lesson_uuid])
                ->first();

            // cek apakah course uuid tersebut ada
            $course = Course::where([
                'uuid' => $getLesson->course_uuid,
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
                    'message' => "Package course not found",
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
                        'message' => 'You can\'t access this course',
                    ]);
                }
            }

            $checkAssignment = StudentAssignment::
            where([
                'student_uuid' => $user->uuid,
                'assignment_uuid' => $getAssignment->uuid,
            ])->first();

            if($checkAssignment){
                if($checkAssignment->status == 'Done'){
                    return response()->json([
                        'message' => 'This assignment is done',
                    ], 200);
                }

                StudentAssignment::
                where([
                    'student_uuid' => $user->uuid,
                    'assignment_uuid' => $getAssignment->uuid,
                ])->update([
                    'assignment_url' => $request->assignment_url,
                    'status' => "Waiting for Review",
                ]);
            }else{
                $assignment = StudentAssignment::
                    create([
                        'student_uuid' => $user->uuid,
                        'assignment_uuid' => $getAssignment->uuid,
                        'assignment_url' => $request->assignment_url,
                        'status' => "Waiting for Review",
                    ]);
            }



            return response()->json([
                'message' => 'Success post assignment',
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
