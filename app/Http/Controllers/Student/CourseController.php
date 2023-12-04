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
use App\Models\PackageCourse;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\StudentProgress;
use App\Models\StudentAssignment;

use App\Traits\Package\PackageTrait;

class CourseController extends Controller
{
    use PackageTrait;

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

    // ambil semua course yang sudah pernah dibeli dari package package
    public function getStudentCourses(){
        $user = JWTAuth::parseToken()->authenticate();
        $uuid_packages = $this->checkAllPurchasedPackageByUser($user);
        $get_course_purchased = PackageCourse::whereIn('package_uuid', $uuid_packages)->with(['course', 'course.instructor', 'course.lessons'])->get();

        $my_courses = [];
        $course_uuids = [];
        foreach ($get_course_purchased as $index => $student_course) {
            if (!in_array($student_course->course_uuid, $course_uuids)) {
                $course_uuids[] = $student_course->course_uuid;
                $my_courses[] = [
                    "course_uuid" => $student_course->course_uuid,
                    "type" => "Lifetime",
                    "title" => $student_course->course->title,
                    'description' => $student_course->course->description,
                    'image' => $student_course->course->image,
                    'video' => $student_course->course->video,
                    'number_of_meeting' => $student_course->course->number_of_meeting,
                    'number_of_lessons'=> count($student_course->course->lessons),
                    'instructor_uuid' => $student_course->course->instructor->name,
                ];
            }
        }

        $uuid_packages = $this->checkAllMembershipPackageByUser($user, $uuid_packages);
        $get_course_membership = PackageCourse::whereIn('package_uuid', $uuid_packages)->with(['course', 'course.instructor', 'course.lessons'])->get();

        foreach ($get_course_membership as $index => $student_course) {
            if (!in_array($student_course->course_uuid, $course_uuids)) {
                $course_uuids[] = $student_course->course_uuid;
                $my_courses[] = [
                    "course_uuid" => $student_course->course_uuid,
                    "type" => "Membership",
                    "title" => $student_course->course->title,
                    'description' => $student_course->course->description,
                    'image' => $student_course->course->image,
                    'video' => $student_course->course->video,
                    'number_of_meeting' => $student_course->course->number_of_meeting,
                    'number_of_lessons'=> count($student_course->course->lessons),
                    'instructor_uuid' => $student_course->course->instructor->name,
                ];
            }
        }

        return response()->json([
            'message'=> "success get data",
            "courses" => $my_courses,
        ], 200);
    }

    public function detailPurchasedCourse($course_uuid){
        $user = JWTAuth::parseToken()->authenticate();

        return $this->checkThisCourseIsPaid($course_uuid, $user);
    }

    public function checkThisCourseIsPaid($course_uuid, $user){
        // cek apakah course uuid tersebut ada
        $course = Course::where([
            'uuid' => $course_uuid,
        ])->first();

        if($course == null){
            return response()->json([
                'message' => "Course not found",
            ]);
        }

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

        $getCourse = Course::where([
            'uuid' => $course_uuid
        ])->with(['instructor', 'lessons', 'pretestPosttests', 'pretestPosttests.test', 'lessons.lectures', 'lessons.quizzes', 'lessons.assignments'])->first();

        $pretest_posttests = [];
        foreach ($getCourse->pretestPosttests as $index => $test) {
            $pretest_posttests[] = [
                "pretest_posttest_uuid" => $test->uuid,
                "title" => $test->test->title,
                "test_uuid" => $test->test->uuid,
                "test_category" => $test->test->test_category,
                "max_attempt" => $test->max_attempt,
            ];
        }

        $lessons = [];
        foreach ($getCourse->lessons as $index => $lesson) {
            $lesson_lectures = [];
            foreach ($lesson->lectures as $index1 => $lecture_data) {
                $isDone = StudentProgress::isLectureDone($lecture_data->uuid, $user->uuid);
                $lesson_lectures[] = [
                    "lecture_uuid" => $lecture_data->uuid,
                    "title" => $lecture_data->title,
                    "is_done" => $isDone ? 1 : 0,
                ];
            }

            $quizzes = [];
            foreach ($lesson->quizzes as $index1 => $quiz) {
                $quizzes[] = [
                    "quiz_uuid" => $quiz->uuid,
                    "test_uuid" => $quiz->test_uuid,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'duration' => $quiz->duration,
                    'max_attempt' => $quiz->max_attempt,
                ];
            }

            $assignments = [];
            foreach ($lesson->assignments as $index1 => $assignment) {
                $student_assignment = StudentAssignment::where([
                    'student_uuid' => $user->uuid,
                    'assignment_uuid' => $assignment->uuid,
                ])->first();
                $status = null;
                if($student_assignment != null){
                    $status = $student_assignment->status;
                }
                $assignments[] = [
                    "assignment_uuid" => $assignment->uuid,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'status' => $status,
                ];
            }


            $lessons[] = [
                "lesson_uuid" => $lesson->uuid,
                "title" => $lesson->title,
                "description" => $lesson->description,
                "lectures" => $lesson_lectures,
                "quizzes" => $quizzes,
                "assignments" => $assignments,
            ];
        }
        $course = [
            "uuid" => $getCourse->uuid,
            "title" => $getCourse->title,
            "instructor_name" => $getCourse->instructor->name,
            'description' => $getCourse->description,
            "image" => $getCourse->image,
            "video" => $getCourse->video,
            'number_of_meeting' => $getCourse->number_of_meeting,
            "lessons" =>$lessons,
            "pretest_posttests" => $pretest_posttests,
        ];

        return $course;
    }
}
