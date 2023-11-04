<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\Course;

class CourseController extends Controller
{
    public function show($package_uuid, $uuid){
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

            $getCourse = Course::
                where(['uuid' => $uuid])
                ->with(['lessons', 'pretestPosttests', 'instructor'])
                ->first();

            if(!$getCourse){
                return response()->json([
                    'message' => "Course not found",
                ], 404);
            }

            $course = [];

            if($getCourse){
                $course= [
                    "course_uuid" => $getCourse->uuid,
                    "package_uuid" => $package_uuid,
                    "title" => $getCourse->title,
                    "description" => $getCourse->description,
                    "image" => $getCourse->image,
                    "video" => $getCourse->video,
                    "instructor_name" => $getCourse->instructor->name,
                    "pretest_posttests" => [],
                    "lessons"=> [],
                ];
                foreach ($getCourse->pretestPosttests as $index => $test) {
                    $course['pretest_posttests'][] = [
                        "pretestpostest_uuid" => $test->uuid,
                        "max_attempt" => $test->max_attempt,
                    ];
                }
                foreach ($getCourse->lessons as $index => $lesson) {
                    $course['lessons'][] = [
                        "lesson_uuid" => $lesson->uuid,
                        "name" => $lesson->name,
                    ];
                }
            }


            return response()->json([
                'message' => 'Success get data',
                'course' => $course,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
