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
use App\Models\PackageCourse;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;

class LessonLectureController extends Controller
{
    public function show($lecture_uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();

            $getLecture = LessonLecture::
                where(['uuid' => $lecture_uuid])
                ->first();

            if(!$getLecture){
                return response()->json([
                    'message' => "Materi tidak ditemukan",
                ], 404);
            }

            $getLesson = CourseLesson::
                where(['uuid' => $getLecture->lesson_uuid])
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
                    'message' => "Paket kursus tidak ditemukan",
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
                        'message' => 'Kamu tidak dapat mengakses kursus ini',
                    ]);
                }
            }

            $lecture = [];

            if($getLecture){
                $lecture= [
                    "lecture_uuid" => $lecture_uuid,
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
                'course_uuid' => $course->uuid,
                'lesson_uuid' => $getLesson->uuid,
                'lecture_uuid' => $lecture_uuid,
            ])->first();

            if(!$check_student_progress){
                StudentProgress::create([
                    'user_uuid' => $user->uuid,
                    'course_uuid' => $course->uuid,
                    'lesson_uuid' => $getLesson->uuid,
                    'lecture_uuid' => $lecture_uuid,
                ]);
            }


            return response()->json([
                'message' => 'Sukses mengambil data',
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
