<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonLecture;
use App\Models\PackageCourse;
use App\Models\CourseLesson;
use App\Models\Course;

class LessonLectureController extends Controller
{
    public function show($lecture_uuid){
        try{
            $getLecture = LessonLecture::
                where(['uuid' => $lecture_uuid])
                ->first();

            if(!$getLecture){
                return response()->json([
                    'message' => "Lecture not found",
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
                    'message' => "Package course not found",
                ]);
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
