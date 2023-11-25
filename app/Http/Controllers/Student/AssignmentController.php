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
use Tymon\JWTAuth\Facades\JWTAuth;

class AssignmentController extends Controller
{
    public function index($package_uuid, $assignment_uuid){
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

            $getAssignment = Assignment::
                select('uuid', 'title', 'description')
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



            $assignment = StudentAssignment::
            select('uuid', 'assignment_url', 'feedback', 'status')
            ->where([
                'student_uuid' => $user->uuid,
                'assignment_uuid' => $getAssignment->uuid,
            ])->get();

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

    public function store(Request $request, $package_uuid, $assignment_uuid){
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
            $getPackage = Package::
                where(['uuid' => $package_uuid])
                ->first();

            if(!$getPackage){
                return response()->json([
                    'message' => "Package not found",
                ], 404);
            }

            $getAssignment = Assignment::
                select('uuid', 'title', 'description')
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

            $checkAssignment = StudentAssignment::
            where([
                'student_uuid' => $user->uuid,
                'assignment_uuid' => $getAssignment->uuid,
            ])->first();

            if(!$checkAssignment){
                return response()->json([
                    'message' => "User already post the assignment",
                ], 404);
            }

            $assignment = StudentAssignment::
            create([
                'student_uuid' => $user->uuid,
                'assignment_uuid' => $getAssignment->uuid,
                'assignment_url' => $request->assignment_url,
                'status' => 0,
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
}
