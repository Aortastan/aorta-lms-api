<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use App\Models\PackageCourse;
use App\Models\LessonLecture;
use App\Models\LessonQuiz;
use App\Models\Assignment;
use App\Models\CourseLesson;
use App\Models\User;
use App\Models\Test;
use App\Models\Tag;
use App\Models\CourseTag;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\StudentProgress;
use App\Models\StudentAssignment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

use App\Traits\Course\CourseTrait;
use App\Traits\Course\DuplicateTrait;

class CourseController extends Controller
{
    use CourseTrait, DuplicateTrait;

    public function index(){
        $search = "";
        $status = "";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['status'])){
            $status = $_GET['status'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getCourses($search, $status, $orderBy, $order);
    }

    public function published(){
        $search = "";
        $status = "Published";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getCourses($search, $status, $orderBy, $order);
    }

    public function preview(Request $request, $course_uuid){
        $user = JWTAuth::parseToken()->authenticate();
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

    public function duplicate(Request $request, $uuid){
        $course = Course::where(['uuid' => $uuid])->first();
        if(!$course){
            return response()->json([
                'message' => 'Course not found',
            ], 404);
        }

        $validate = [
            'title' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->duplicateCourse($request, $uuid);
    }

    public function show(Request $request, $uuid){
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

            $course->have_image = 0;
            $course->have_video = 0;
            if($course->image){
                $course->have_image = 1;
            }
            if($course->video){
                $course->have_video = 1;
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
                                    ->select('pretest_posttests.uuid', 'pretest_posttests.max_attempt', 'pretest_posttests.duration', 'tests.test_type as test_type', 'tests.title as test_title', 'tests.test_category as test_category',  'tests.uuid as test_uuid')
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

    public function store(Request $request){
        $validate = [
            'title' => 'required',
            'description' => 'required',
            'image' => 'required|image',
            'number_of_meeting' => 'required|numeric',
            'is_have_pretest_posttest' => 'required',
            'instructor_uuid' => 'required',
            'have_image' => 'required|boolean',
            'have_video' => 'required|boolean',
        ];

        if(isset($request->have_video)){
            if($request->have_video == 1){
                $validate['video'] = 'required|mimetypes:video/*';
            }
        }

        if(isset($request->have_image)){
            if($request->have_image == 1){
                $validate['image'] = 'required|image';
            }
        }

        if($request->is_have_pretest_posttest == 1){
            $validate['pretest_posttests'] = 'required|array';
            $validate['pretest_posttests.*.max_attempt'] = 'required|numeric';
            $validate['pretest_posttests.*.duration'] = 'required|numeric';
            $validate['pretest_posttests.*.test_uuid'] = 'required';
        }
        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkInstructor = User::where(['uuid' => $request->instructor_uuid, 'role' => 'instructor'])->first();
        if(!$checkInstructor){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => "Instructor not found",
            ], 422);
        }

        $pathVideo = null;
        $pathImage = $request->image->store('courses', 'public');
        if($request->video != null){
            $pathVideo = $request->video->store('courses', 'public');
        }

        $pathImage = "";
        $pathVideo = "";
        if($request->have_image == 1){
            $pathImage = $request->image->store('courses', 'public');
        }

        if($request->have_video == 1){
            $pathVideo = $request->video->store('courses', 'public');
        }

        $validated = [
            'title' => $request->title,
            'description' => $request->description,
            'image' => $pathImage,
            'video' => $pathVideo,
            'number_of_meeting' => $request->number_of_meeting,
            'is_have_pretest_posttest' => $request->is_have_pretest_posttest,
            'instructor_uuid' => $request->instructor_uuid,
            'status' => 'draft',
        ];

        $course = Course::create($validated);

        if($request->is_have_pretest_posttest == 1){
            $validated_pretest_posttests = [];
            foreach ($request->pretest_posttests as $index => $pretest_posttest) {
                $checkTest = Test::where('uuid', $pretest_posttest['test_uuid'])->first();
                if(!$checkTest){
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => "Test not found",
                    ], 404);
                }
                $validated_pretest_posttests[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'course_uuid' => $course->uuid,
                    'test_uuid' => $checkTest->uuid,
                    'max_attempt' => $pretest_posttest['max_attempt'],
                    'duration' => $pretest_posttest['duration'],
                ];
            }
            PretestPosttest::insert($validated_pretest_posttests);
        }

        return response()->json([
            'message' => 'Success create new course'
        ], 200);
    }

    public function update(Request $request, $uuid){
        $checkCourse = Course::where([
            'uuid' => $uuid
        ])->first();

        if(!$checkCourse){
            return response()->json([
                'message' => 'Course not found'
            ], 404);
        }
        $validate = [
            'title' => 'required',
            'description' => 'required',
            'number_of_meeting' => 'required|numeric',
            'is_have_pretest_posttest' => 'required',
            'instructor_uuid' => 'required',
            'status' => 'required|in:Published,Waiting for review,hold,Draft',
            'have_image' => 'required|boolean',
            'have_video' => 'required|boolean',
        ];

        if(isset($request->have_video)){
            if($request->have_video == 1){
                if(!is_string($request->video)){
                    $validate['video'] = 'required|mimetypes:video/*';
                }
            }
        }

        if(isset($request->have_image)){
            if($request->have_image == 1){
                if(!is_string($request->image)){
                    $validate['image'] = 'required|image';
                }
            }
        }

        if($request->is_have_pretest_posttest == 1){
            $validate['pretest_posttests'] = 'required|array';
            $validate['pretest_posttests.*.uuid'] = 'required';
            $validate['pretest_posttests.*.max_attempt'] = 'required|numeric';
            $validate['pretest_posttests.*.duration'] = 'required|numeric';
            $validate['pretest_posttests.*.test_uuid'] = 'required';
        }
        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkInstructor = User::where(['uuid' => $request->instructor_uuid, 'role' => 'instructor'])->first();
        if(!$checkInstructor){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => "Instructor not found",
            ], 422);
        }

        $pathImage = "";
        $pathVideo = "";
        if($request->have_image == 1){
            $pathImage = $checkCourse->image;
            if(!is_string($request->image)){
                $pathImage = $request->image->store('courses', 'public');
                if (File::exists(public_path('storage/'.$checkCourse->image))) {
                    File::delete(public_path('storage/'.$checkCourse->image));
                }
            }
        }else{
            if (File::exists(public_path('storage/'.$checkCourse->image))) {
                File::delete(public_path('storage/'.$checkCourse->image));
            }
        }

        if($request->have_video == 1){
            $pathVideo = $checkCourse->video;
            if(!is_string($request->video)){
                $pathVideo = $request->video->store('courses', 'public');
                if (File::exists(public_path('storage/'.$checkCourse->video))) {
                    File::delete(public_path('storage/'.$checkCourse->video));
                }
            }
        }else{
            if (File::exists(public_path('storage/'.$checkCourse->video))) {
                File::delete(public_path('storage/'.$checkCourse->video));
            }
        }

        $validated = [
            'title' => $request->title,
            'description' => $request->description,
            'image' => $pathImage,
            'video' => $pathVideo,
            'number_of_meeting' => $request->number_of_meeting,
            'is_have_pretest_posttest' => $request->is_have_pretest_posttest,
            'instructor_uuid' => $request->instructor_uuid,
            'status' => $request->status,
        ];

        $course = Course::where(['uuid' => $uuid])->update($validated);
        $pretestUuid = [];
        $newPretests = [];
        if($request->is_have_pretest_posttest == 1){
            foreach ($request->pretest_posttests as $index => $test) {
                $checkTest = Test::where('uuid', $test['test_uuid'])->first();
                if(!$checkTest){
                    return response()->json([
                        'message' => 'Validation failed',
                        'errors' => "Test not found",
                    ], 404);
                }

                $checkPretestPosttest = PretestPosttest::where('uuid', $test['uuid'])->first();

                if(!$checkPretestPosttest){
                        $newPretests[]=[
                            'uuid' => Uuid::uuid4()->toString(),
                            'course_uuid' => $checkCourse->uuid,
                            'test_uuid' => $test['test_uuid'],
                            'max_attempt' => $test['max_attempt'],
                            'duration' => $test['duration'],
                        ];
                }else{
                    $pretestUuid[] = $checkPretestPosttest->uuid;

                    $validatedPretest=[
                        'test_uuid' => $test['test_uuid'],
                        'max_attempt' => $test['max_attempt'],
                        'duration' => $test['duration'],
                    ];
                    PretestPosttest::where('uuid', $checkPretestPosttest->uuid)->update($validatedPretest);
                }
            }
        }else{
            PretestPosttest::where(['course_uuid' => $uuid])->delete();
        }

        Course::where('uuid', $uuid)->update($validated);
        PretestPosttest::where(['course_uuid' => $uuid])->whereNotIn('uuid', $pretestUuid)->delete();
        if($request->is_have_pretest_posttest == 1){
            if(count($newPretests) > 0){
                PretestPosttest::insert($newPretests);
            }
        }

        return response()->json([
            'message' => 'Success update course'
        ], 200);
    }

    public function updateTag(Request $request, $uuid){
        $checkCourse = Course::where([
            'uuid' => $uuid
        ])->first();

        if(!$checkCourse){
            return response()->json([
                'message' => 'Course not found'
            ], 404);
        }
        $validate = [
            'tags' => 'required|array',
            'tags.*.uuid' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $course_tags = [];
        foreach ($request->tags as $index => $tag_uuid) {
            $checkTag = Tag::where([
                'uuid' => $tag_uuid,
            ])->first();

            if(!$checkTag){
                return response()->json([
                    'message' => 'Tag not found',
                ], 404);
            }
            $course_tags[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'course_uuid' => $checkCourse->uuid,
                'tag_uuid' => $checkTag->uuid,
            ];
        }

        CourseTag::where([
            'course_uuid' => $checkCourse->uuid,
        ])->delete();

        if(count($course_tags) > 0){
            CourseTag::insert($course_tags);
        }

        return response()->json([
            'message' => 'Success update tag',
        ], 200);

    }

    public function delete($uuid){
        $check_course = Course::where([
            'uuid' => $uuid,
        ])->first();

        if($check_course == null){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $check_package_course = PackageCourse::where([
            'course_uuid' => $uuid,
        ])->first();

        if($check_package_course){
            return response()->json([
                'message' => 'This course has published and used in package. You can\'t delete it',
            ], 404);
        }

        $check_lesson = CourseLesson::where([
            'course_uuid' => $uuid,
        ])->get();

        foreach ($check_lesson as $index => $lesson) {
            LessonLecture::where([
                'lesson_uuid' => $lesson->uuid,
            ])->delete();

            LessonQuiz::where([
                'lesson_uuid' => $lesson->uuid,
            ])->delete();

            Assignment::where([
                'lesson_uuid' => $lesson->uuid,
            ])->delete();
        }

        CourseLesson::where([
            'course_uuid' => $uuid,
        ])->delete();

        PretestPosttest::where([
            'course_uuid' => $uuid,
        ])->delete();


        return response()->json([
            'message' => 'Delete succesfully',
        ], 200);
    }
}
