<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use App\Models\User;
use App\Models\Test;
use App\Models\Tag;
use App\Models\CourseTag;
use App\Models\CourseLesson;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

class CourseController extends Controller
{
    public function index(){
        try{
            $courses = DB::table('courses')
                ->select('courses.uuid', 'courses.title', 'courses.description', 'courses.image', 'courses.video', 'courses.number_of_meeting', 'courses.is_have_pretest_posttest', 'courses.status', 'users.name as instructor_name')
                ->join('users', 'courses.instructor_uuid', '=', 'users.uuid')
                ->get();

            return response()->json([
                'message' => 'Success get data',
                'courses' => $courses,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
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
                        ];
                }else{
                    $pretestUuid[] = $checkPretestPosttest->uuid;

                    $validatedPretest=[
                        'test_uuid' => $test['test_uuid'],
                        'max_attempt' => $test['max_attempt'],
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
}
