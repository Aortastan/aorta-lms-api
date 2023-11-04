<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonLecture;
use App\Models\StudentProgress;

class LessonLectureController extends Controller
{
    public function show($package_uuid, $course_uuid, $lesson_uuid, $lecture_uuid){
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
                where(['uuid' => $course_uuid])
                ->first();

            if(!$getCourse){
                return response()->json([
                    'message' => "Course not found",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $lesson_uuid])
                ->first();

            if(!$getLesson){
                return response()->json([
                    'message' => "Lesson not found",
                ], 404);
            }

            $getLecture = LessonLecture::
                where(['uuid' => $lecture_uuid])
                ->first();

            if(!$getLecture){
                return response()->json([
                    'message' => "Lecture not found",
                ], 404);
            }

            $lecture = [];

            if($getLecture){
                $lecture= [
                    "lecture_uuid" => $lecture_uuid,
                    "lesson_uuid" => $lesson_uuid,
                    "package_uuid" => $package_uuid,
                    "course_uuid" => $course_uuid,
                    "title" => $getLecture->title,
                    "body" => $getLecture->body,
                    "file_path" => $getLecture->file_path,
                    "url_path" => $getLecture->url_path,
                    "file_duration" => $getLecture->file_duration,
                    "file_duration_seconds" => $getLecture->file_duration_seconds,
                    "type" => $getLecture->type,
                ];
            }

            $check_student_progress = StudentProgress::where([
                'user_uuid' => $user->uuid,
                'package_uuid' => $package_uuid,
                'course_uuid' => $course_uuid,
                'lesson_uuid' => $lesson_uuid,
                'lecture_uuid' => $lecture_uuid,
            ])->first();

            if(!$check_student_progress){
                StudentProgress::create([
                    'user_uuid' => $user->uuid,
                    'package_uuid' => $package_uuid,
                    'course_uuid' => $course_uuid,
                    'lesson_uuid' => $lesson_uuid,
                    'lecture_uuid' => $lecture_uuid,
                ]);
            }


            return response()->json([
                'message' => 'Success get data',
                'lecture' => $lecture,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
