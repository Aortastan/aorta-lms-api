<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\Course;
use App\Models\CourseLesson;

class CourseLessonController extends Controller
{
    public function show($package_uuid, $course_uuid, $uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $getPackage = Package::
                where(['uuid' => $package_uuid])
                ->first();

            if(!$getPackage){
                return response()->json([
                    'message' => "Paket tidak ditemukan",
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
                    'message' => "Kursus tidak ditemukan",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $uuid])
                ->with(['lectures', 'quizzes', 'assignments'])
                ->first();

            if(!$getLesson){
                return response()->json([
                    'message' => "Lesson tidak ditemukan",
                ], 404);
            }

            $lesson = [];

            if($getLesson){
                $lesson= [
                    "lesson_uuid" => $getLesson->uuid,
                    "package_uuid" => $package_uuid,
                    "course_uuid" => $course_uuid,
                    "name" => $getLesson->name,
                    "description" => $getLesson->description,
                    "quizzes" => [],
                    "assignments"=> [],
                    "lectures"=> [],
                ];
                foreach ($getLesson->quizzes as $index => $test) {
                    $lesson['quizzes'][] = [
                        "quiz_uuid" => $test->uuid,
                        "name" => $test->name,
                        "max_attempt" => $test->max_attempt,
                    ];
                }
                foreach ($getLesson->assignments as $index => $assignment) {
                    $lesson['assignments'][] = [
                        "assignment_uuid" => $assignment->uuid,
                        "name" => $assignment->name,
                    ];
                }
                foreach ($getLesson->lectures as $index => $lecture) {
                    $lesson['lectures'][] = [
                        "lecture_uuid" => $lecture->uuid,
                        "title" => $lecture->title,
                    ];
                }
            }


            return response()->json([
                'message' => 'Sukses mengambil data',
                'lesson' => $lesson,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
