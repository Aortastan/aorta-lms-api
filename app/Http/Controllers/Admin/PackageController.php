<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Package;
use App\Models\Category;
use App\Models\Course;
use App\Models\Test;
use App\Models\PackageTest;
use App\Models\PackageCourse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

class PackageController extends Controller
{
    public function index(){
        try{
            $getPackages = Package::with(['category'])->get();
            $packages = [];

            foreach ($getPackages as $index => $package) {
                $packages[] = [
                    'uuid' => $package->uuid,
                    'package_type' => $package->package_type,
                    'category_name' => $package->category->name,
                    'name' => $package->name,
                    'price_lifetime' => $package->price_lifetime,
                    'price_one_month' => $package->price_one_month,
                    'price_three_months' => $package->price_three_months,
                    'price_six_months' => $package->price_six_months,
                    'price_one_year' => $package->price_one_year,
                    'learner_accesibility' => $package->learner_accesibility,
                    'image' => $package->image,
                    'discount' => $package->discount,
                    'is_membership' => $package->is_membership,
                ];
            }

            return response()->json([
                'message' => 'Success get data',
                'packages' => $packages,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show($uuid){
        $checkPackage = Package::where(['uuid' => $uuid])->with(['packageTests', 'packageTests.test', 'packageCourses', 'packageCourses.course'])->first();

        if(!$checkPackage){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }
        $package=[
            "name" => $checkPackage->name,
            "package_type" => $checkPackage->package_type,
            "price_lifetime" => $checkPackage->price_lifetime,
            "price_one_month" => $checkPackage->price_one_month,
            "price_three_months" => $checkPackage->price_three_months,
            "price_six_months" => $checkPackage->price_six_months,
            "price_one_year" => $checkPackage->price_one_year,
            'learner_accesibility' => $checkPackage->learner_accesibility,
            'image' => $checkPackage->image,
            'discount' => $checkPackage->discount,
            'is_membership' => $checkPackage->is_membership,
            'status' => $checkPackage->status,
        ];

        if($checkPackage->package_type == "course"){
            $package['package_courses'] = [];
            foreach ($checkPackage->packageCourses as $index2 => $list) {
                $package['package_courses'][] = [
                    "title" => $list['course']['title'],
                    "description" => $list['course']['description'],
                    "status" => $list['status'],
                    "image" => $list['course']['image'],
                ];
            }
        }elseif($checkPackage->package_type == "test"){
            $package['package_tests'] = [];
            foreach ($checkPackage->packageTests as $index2 => $list) {
                $package['package_tests'][] = [
                    "name" => $list['test']['name'],
                    "test_type" => $list['test']['test_type'],
                    "attempt" => $list['attempt'],
                    "passing_grade" => $list['passing_grade'],
                    "duration" => $list['duration'],
                ];
            }
        }

        return response()->json([
            'message' => 'Successful get data',
            'package' => $package,
        ], 200);
    }

    public function store(Request $request){
        $validate = [
            'category_uuid' => 'required',
            'package_type' => 'required|in:course,test',
            'name' => 'required',
            'price_lifetime' => 'required|numeric',
            'price_one_month' => 'required|numeric',
            'price_three_months' => 'required|numeric',
            'price_six_months' => 'required|numeric',
            'price_one_year' => 'required|numeric',
            'learner_accesibility' => 'required|in:paid,free',
            'image' => 'required|image',
            'discount' => 'required|numeric',
            'is_membership' => 'required',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkCategory = Category::where(['uuid' => $request->category_uuid])->first();
        if(!$checkCategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ["category_uuid" => ["Category not found"]],
            ], 422);
        }

        $path = $request->image->store('packages', 'public');

        $validated = [
            'category_uuid' => $request->category_uuid,
            'package_type' => $request->package_type,
            'name' => $request->name,
            'price_lifetime' => $request->price_lifetime,
            'price_one_month' => $request->price_one_month,
            'price_three_months' => $request->price_three_months,
            'price_six_months' => $request->price_six_months,
            'price_one_year' => $request->price_one_year,
            'learner_accesibility' => $request->learner_accesibility,
            'image' => $path,
            'discount' => $request->discount,
            'is_membership' => $request->is_membership,
            'status' => true,
        ];

        Package::create($validated);

        return response()->json([
            'message' => 'Success create new package'
        ], 200);
    }

    public function update(Request $request, $type, $uuid){
        if($type != 'course' && $type != 'test'){
            return response()->json([
                'message' => 'Type not valid'
            ], 422);
        }
        $checkPackage = Package::where(['uuid' => $uuid, "package_type" => $type])->first();

        if(!$checkPackage){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }

        $validate = [
            'category_uuid' => 'required',
            'name' => 'required',
            'price_lifetime' => 'required|numeric',
            'price_one_month' => 'required|numeric',
            'price_three_months' => 'required|numeric',
            'price_six_months' => 'required|numeric',
            'price_one_year' => 'required|numeric',
            'learner_accesibility' => 'required|in:paid,free',
            'image' => 'required',
            'discount' => 'required|numeric',
            'is_membership' => 'required',
            'status' => 'required'
        ];

        if($request->image instanceof \Illuminate\Http\UploadedFile && $request->image->isValid()){
            $validate['image'] = "required|image";
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkCategory = Category::where(['uuid' => $request->category_uuid])->first();
        if(!$checkCategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ["category_uuid" => ["Category not found"]],
            ], 422);
        }

        $path = $checkPackage->image;
        if($request->image instanceof \Illuminate\Http\UploadedFile && $request->image->isValid()){
            $path = $request->image->store('packages', 'public');
            if (File::exists(public_path('storage/'.$checkPackage->image))) {
                File::delete(public_path('storage/'.$checkPackage->image));
            }
        }


        $validated = [
            'category_uuid' => $request->category_uuid,
            'name' => $request->name,
            'price_lifetime' => $request->price_lifetime,
            'price_one_month' => $request->price_one_month,
            'price_three_months' => $request->price_three_months,
            'price_six_months' => $request->price_six_months,
            'price_one_year' => $request->price_one_year,
            'learner_accesibility' => $request->learner_accesibility,
            'image' => $path,
            'discount' => $request->discount,
            'is_membership' => $request->is_membership,
            'status' => $request->status,
        ];

        Package::where(['uuid' => $uuid])->update($validated);

        return response()->json([
            'message' => 'Success update package'
        ], 200);
    }

    public function packageLists(Request $request, $type, $uuid): JsonResponse{
        if($type != 'course' && $type != 'test'){
            return response()->json([
                'message' => 'Type not valid'
            ], 422);
        }
        $checkPackage = Package::where(['uuid' => $uuid, "package_type" => $type])->first();

        if(!$checkPackage){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }

       if($type == 'course'){
            $validate = [
                'lists' => 'required|array',
                'lists.*' => 'required',
                'lists.*.uuid' => 'required',
                'lists.*.course_uuid' => 'required',
                'lists.*.status' => 'required',
            ];

            $validator = Validator::make($request->all(), $validate);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }

            $listsUuid = [];
            $newCourses = [];

            foreach ($request->lists as $index => $list) {
                $checkCourse = Course::where(['uuid' => $list['course_uuid']])->first();
                if(!$checkCourse){
                    return response()->json([
                        'message' => 'Course not found',
                    ], 404);
                }
                $checkList = PackageCourse::where('uuid', $list['uuid'])->first();

                if(!$checkList){
                        $newCourses[]=[
                            'uuid' => Uuid::uuid4()->toString(),
                            'package_uuid' => $checkPackage->uuid,
                            'course_uuid' => $list['course_uuid'],
                            'status' => $list['status'],
                        ];
                }else{
                    $listsUuid[] = $list['uuid'];
                    $validatedList=[
                        'status' => $list['status'],
                    ];
                    PackageCourse::where('uuid', $list['uuid'])->update($validatedList);
                }
            }

            PackageCourse::where(['package_uuid' => $uuid])->whereNotIn('uuid', $listsUuid)->delete();
            if(count($newCourses) > 0){
                PackageCourse::insert($newCourses);
            }













       }elseif($type == 'test'){
            $validate = [
                'lists' => 'required|array',
                'lists.*' => 'required',
                'lists.*.uuid' => 'required',
                'lists.*.test_uuid' => 'required',
                'lists.*.attempt' => 'required',
                'lists.*.passing_grade' => 'required|numeric',
                'lists.*.duration' => 'required',
            ];

            $validator = Validator::make($request->all(), $validate);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ]);
            }

            $listsUuid = [];
            $newLists = [];

            foreach ($request->lists as $index => $list) {
                $checkTest = Test::where(['uuid' => $list['test_uuid']])->first();
                if(!$checkTest){
                    return response()->json([
                        'message' => 'Test not found',
                    ], 404);
                }
                $checkList = PackageTest::where('uuid', $list['uuid'])->first();

                if(!$checkList){
                        $newLists[]=[
                            'uuid' => Uuid::uuid4()->toString(),
                            'package_uuid' => $checkPackage->uuid,
                            'test_uuid' => $list['test_uuid'],
                            'attempt' => $list['attempt'],
                            'passing_grade' => $list['passing_grade'],
                            'duration' => $list['duration'],
                        ];
                }else{
                    $listsUuid[] = $list['uuid'];
                    $validatedList=[
                        'attempt' => $list['attempt'],
                        'passing_grade' => $list['passing_grade'],
                        'duration' => $list['duration'],
                    ];
                    PackageTest::where('uuid', $list['uuid'])->update($validatedList);
                }
            }

            PackageTest::where(['package_uuid' => $uuid])->whereNotIn('uuid', $listsUuid)->delete();
            if(count($newLists) > 0){
                PackageTest::insert($newLists);
            }
       }

        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }
}
