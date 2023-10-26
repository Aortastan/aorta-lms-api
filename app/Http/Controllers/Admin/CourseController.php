<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use App\Models\User;
use App\Models\Test;
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
            $courseLessons = DB::table('course_lessons')
                    ->select('uuid', 'name', 'status')
                    ->where('course_uuid', $uuid)
                    ->get();

            $course->course_lessons = $courseLessons;

            $coursePretestPosttest =  DB::table('pretest_posttests')
                                    ->select('pretest_posttests.uuid', 'pretest_posttests.max_attempt', 'tests.name as test_name')
                                    ->join('tests', 'pretest_posttests.test_uuid', '=', 'tests.uuid')
                                    ->where('course_uuid', $uuid)
                                    ->get();

                                    $course->course_pretest_posttests = $coursePretestPosttest;

            if(!$course){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
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

    public function store(Request $request){
        $validate = [
            'title' => 'required',
            'description' => 'required',
            'image' => 'required|image',
            'number_of_meeting' => 'required|numeric',
            'is_have_pretest_posttest' => 'required',
            'instructor_uuid' => 'required',
        ];

        if(isset($request->video)){
            if($request->video != null){
                $validate['video'] = 'required|mimetypes:video/*';
            }
        }else{
            $validate['video'] = 'required';
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
            'status' => 'required|in:pending,published,waiting for review,hold,draft',
        ];

        if(isset($request->video)){
            if(!is_string($request->video)){
                $validate['video'] = 'required|mimetypes:video/*';
            }
        }else{
            $validate['video'] = 'required';
        }
        if(isset($request->image)){
            if(!is_string($request->image)){
                $validate['image'] = 'required|image';
            }
        }else{
            $validate['image'] = 'required';
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

        $pathImage = $checkCourse->image;
        $pathVideo = $checkCourse->video;
        if(!is_string($request->image)){
            $pathImage = $request->image->store('courses', 'public');
            if (File::exists(public_path('storage/'.$checkCourse->image))) {
                File::delete(public_path('storage/'.$checkCourse->image));
            }
        }
        if($request->video == null){
            if (File::exists(public_path('storage/'.$checkCourse->video))) {
                File::delete(public_path('storage/'.$checkCourse->video));
            }
        }else{
            if(!is_string($request->video)){
                $pathVideo = $request->video->store('courses', 'public');
                if (File::exists(public_path('storage/'.$checkCourse->video))) {
                    File::delete(public_path('storage/'.$checkCourse->video));
                }
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
}
