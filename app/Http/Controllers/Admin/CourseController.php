<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\Course;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

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

    public function store(Request $request): JsonResponse{
        $validate = [
            'title' => 'required',
            'description' => 'required',
            'image' => 'required|image',
            'number_of_meeting' => 'required',
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
            $validate['pretest_posttests.*.uuid'] = 'required';
            $validate['pretest_posttests.*.max_attempt'] = 'required';
        }
        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkInstructor = User::where(['uuid' => $request->istructor_uuid, 'role' => 'instructor'])->first();
        if(!$checkInstructor){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => "Instructor not found",
            ], 422);
        }

        if($request->is_have_pretest_posttest == 1){
            $listUuidPretestPosttests = [];

            foreach ($request->pretest_posttests as $index => $pretest_posttest) {
                $listUuidPretestPosttests[] = $pretest_posttest->uuid;
            }
            $checkPretestPosttests = PretestPosttest::whereIn('uuid', $listUuidPretestPosttests)->count();
            if($checkPretestPosttests != count($request->pretest_posttests)){
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => "Test not valid",
                ], 422);
            }
        }
        $pathVideo = null;
        $pathImage = $request->image->store('courses', 'public');
        if($request->video != null){
            $pathVideo = $request->video->store('courses', 'public');
        }

        $validated = [
            'user_uuid' => $user->uuid,
            'title' => $request->title,
            'category_uuid' => $request->category_uuid,
            'slug' => $request->slug,
            'body' => $request->body,
            'image' => $path,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
        ];

        $course = Blog::create($validated);

        if($request->is_have_pretest_posttest == 1){
            $validated_pretest_posttests = [];
            foreach ($request->pretest_posttests as $index => $pretest_posttest) {
                $validated_pretest_posttests[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'course_uuid' => $course->uuid,
                    'test_uuid' => $pretest_posttest->uuid,
                    'max_attempt' => $pretest_posttest->max_attempt,
                ];
            }

            PretestPosttest::insert($validated_pretest_posttests);
        }

        return response()->json([
            'message' => 'Success create new course'
        ], 200);
    }
}
