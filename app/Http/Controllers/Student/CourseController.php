<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\CourseLesson;
use App\Models\CourseTag;
use App\Models\Course;

class CourseController extends Controller
{
    public function show($package_uuid, $uuid){
        // try{
        //     // $user = JWTAuth::parseToken()->authenticate();
        //     $getPackage = Package::
        //         where(['uuid' => $package_uuid])
        //         ->first();

        //     if(!$getPackage){
        //         return response()->json([
        //             'message' => "Package not found",
        //         ], 404);
        //     }

            // $check_purchased_package = DB::table('purchased_packages')
            //     ->where(['purchased_packages.user_uuid' => $user->uuid, 'purchased_packages.package_uuid' => $getPackage->uuid])
            //     ->first();

            // if(!$check_purchased_package){
            //     $check_membership_history = DB::table('membership_histories')
            //         ->where(['membership_histories.user_uuid' => $user->uuid, 'membership_histories.package_uuid' => $getPackage->uuid])
            //         ->whereDate('membership_histories.expired_date', '>', now())
            //         ->first();

            //     if(!$check_membership_history){
            //         return response()->json([
            //             'message' => "You haven't purchased this package yet",
            //         ], 404);
            //     }
            // }

            // $getCourse = Course::
            //     where(['uuid' => $uuid])
            //     ->with(['lessons', 'pretestPosttests', 'instructor'])
            //     ->first();

            // if(!$getCourse){
            //     return response()->json([
            //         'message' => "Course not found",
            //     ], 404);
            // }

        //     $course = [];

        //     if($getCourse){
        //         $course= [
        //             "course_uuid" => $getCourse->uuid,
        //             "package_uuid" => $package_uuid,
        //             "title" => $getCourse->title,
        //             "description" => $getCourse->description,
        //             "image" => $getCourse->image,
        //             "video" => $getCourse->video,
        //             "instructor_name" => $getCourse->instructor->name,
        //             "pretest_posttests" => [],
        //             "lessons"=> [],
        //         ];
        //         foreach ($getCourse->pretestPosttests as $index => $test) {
        //             $course['pretest_posttests'][] = [
        //                 "pretestpostest_uuid" => $test->uuid,
        //                 "max_attempt" => $test->max_attempt,
        //             ];
        //         }
        //         foreach ($getCourse->lessons as $index => $lesson) {
        //             $course['lessons'][] = [
        //                 "lesson_uuid" => $lesson->uuid,
        //                 "name" => $lesson->name,
        //             ];
        //         }
        //     }


        //     return response()->json([
        //         'message' => 'Success get data',
        //         'course' => $course,
        //     ], 200);
        // }
        // catch(\Exception $e){
        //     return response()->json([
        //         'message' => $e,
        //     ], 404);
        // }

        try{
            $course = DB::table('courses')
                    ->select('courses.uuid', 'courses.title', 'courses.description', 'courses.image', 'courses.video', 'courses.number_of_meeting', 'courses.is_have_pretest_posttest', 'courses.status', 'users.name as instructor_name')
                    ->join('users', 'courses.instructor_uuid', '=', 'users.uuid')
                    ->where(['courses.uuid' => $uuid])
                    ->first();

            if($course == null){
                return response()->json([
                    'message' => "Course not found",
                ], 404);
            }

            $getCourseLessons = CourseLesson::
                    select('uuid', 'title')
                    ->where('course_uuid', $uuid)
                    ->with(['lectures'])
                    ->get();

            $courseLessons = [];
            foreach ($getCourseLessons as $index => $lesson) {
                $lectures = [];
                foreach ($lesson->lectures as $index1 => $lecture) {
                    $lectures[] = [
                        'lecture_uuid' => $lecture->uuid,
                        'title' => $lecture->title,
                    ];
                }
                $courseLessons[] = [
                    'lesson_uuid' => $lesson->uuid,
                    'title' => $lesson->title,
                    'lectures' => $lectures,
                ];
            }

            $getCourseTags = CourseTag::where([
                'course_uuid' => $course->uuid,
            ])->with(['tag'])->get();

            $courseTags = [];
            foreach ($getCourseTags as $index => $tag) {
                $courseTags[] = [
                    'tag_uuid' => $tag->tag->uuid,
                    'name' => $tag->tag->name,
                ];
            }

            $course->course_lessons = $courseLessons;
            $course->course_tags = $courseTags;

            $coursePretestPosttest =  DB::table('pretest_posttests')
                                    ->select('pretest_posttests.uuid', 'pretest_posttests.max_attempt', 'tests.test_type as test_type', 'tests.title as test_title', 'tests.test_category as test_category', 'tests.uuid as test_uuid')
                                    ->join('tests', 'pretest_posttests.test_uuid', '=', 'tests.uuid')
                                    ->where('course_uuid', $uuid)
                                    ->get();

                                    $course->course_pretest_posttests = $coursePretestPosttest;

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
