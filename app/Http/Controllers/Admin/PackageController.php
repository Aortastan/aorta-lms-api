<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Package;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Course;
use App\Models\Test;
use App\Models\Tryout;
use App\Models\PackageTest;
use App\Models\PackageCourse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

use App\Traits\Package\PackageTrait;

class PackageController extends Controller
{
    use PackageTrait;
    public function index(){
        return $this->getAllPackages(true);
    }

    public function show($uuid){
        $checkPackage = Package::where(['uuid' => $uuid])->with(['packageTests', 'packageTests.test', 'packageCourses', 'packageCourses.course', 'category', 'subcategory'])->first();

        if(!$checkPackage){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }
        $package=[
            "name" => $checkPackage->name,
            "description" => $checkPackage->description,
            "package_type" => $checkPackage->package_type,
            "category_name" => $checkPackage->category->name,
            "subcategory_name" => $checkPackage->subcategory->name,
            "category_uuid" => $checkPackage->category->uuid,
            "subcategory_uuid" => $checkPackage->subcategory->uuid,
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
            'test_type' => $checkPackage->test_type,
            // 'max_point' => $checkPackage->max_point,
        ];

        if($checkPackage->package_type == "course"){
            $package['package_courses'] = [];
            foreach ($checkPackage->packageCourses as $index2 => $list) {
                $package['package_courses'][] = [
                    "course_uuid" => $list['course']['uuid'],
                    "title" => $list['course']['title'],
                    "description" => $list['course']['description'],
                    "status" => $list['status'],
                    "image" => $list['course']['image'],
                ];
            }
        }

        $package['package_tests'] = [];
        foreach ($checkPackage->packageTests as $index2 => $list) {

            $test = $list->test ?? $list->delyn;

            $package['package_tests'][] = [
                "uuid" => $list['uuid'],
                "test_uuid" => $test->uuid ?? null,
                "title" => $test->title ?? null,
                "attempt" => $list['attempt'],
                "duration" => $list['duration'],
                "max_point" => $list['max_point'],
            ];
        }
        return response()->json([
            'message' => 'Successful get data',
            'package' => $package,
        ], 200);
    }

    public function store(Request $request){
        $validate = [
            'category_uuid' => 'required',
            'subcategory_uuid' => 'required',
            'package_type' => 'required|in:course,test',
            'name' => 'required',
            'description' => 'required',
            'learner_accesibility' => 'required|in:paid,free',
            'image' => 'required|image',
        ];

        if($request->test_type != null){
            $validate['test_type'] = 'required|in:classical,IRT,Tes Potensi,TSKKWK';
        }

        if($request->learner_accesibility == 'paid'){
            $validate['price_lifetime'] = 'required|numeric';
            $validate['price_one_month'] = 'required|numeric';
            $validate['price_three_months'] = 'required|numeric';
            $validate['price_six_months'] = 'required|numeric';
            $validate['price_one_year'] = 'required|numeric';
            $validate['discount'] = 'required|numeric';
            $validate['is_membership'] = 'required|boolean';
        }

        // if($request->test_type == 'IRT'){
        //     $validate['max_point'] = 'required|numeric';
        // }

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

        $checkSubcategory = Subcategory::where(['uuid' => $request->subcategory_uuid])->first();
        if(!$checkSubcategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ["subcategory_uuid" => ["Subcategory not found"]],
            ], 422);
        }

        $path = $request->image->store('packages', 'public');

        $validated = [
            'category_uuid' => $request->category_uuid,
            'subcategory_uuid' => $request->subcategory_uuid,
            'package_type' => $request->package_type,
            'learner_accesibility' => $request->learner_accesibility,
            'name' => $request->name,
            'description' => $request->description,
            'status' => "Draft",
            'image' => $path,
        ];

        if($request->learner_accesibility == 'paid'){
            $validated['price_lifetime'] = $request->price_lifetime;
            $validated['price_one_month'] = $request->price_one_month;
            $validated['price_three_months'] = $request->price_three_months;
            $validated['price_six_months'] = $request->price_six_months;
            $validated['price_one_year'] = $request->price_one_year;
            $validated['discount'] = $request->discount;
            $validated['is_membership'] = $request->is_membership;
        }else{
            $validated['price_lifetime'] = 0;
            $validated['price_one_month'] = 0;
            $validated['price_three_months'] = 0;
            $validated['price_six_months'] = 0;
            $validated['price_one_year'] = 0;
            $validated['discount'] = 0;
            $validated['is_membership'] = 0;
        }

        $validated['test_type'] =  null;

        if($request->test_type){
            $validated['test_type'] = $request->test_type;
        }

        // $validated['max_point'] = null;
        // if($request->test_type == 'IRT'){
        //      $validated['max_point'] = $request->max_point;
        // }

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
            'category_uuid' => 'required|string',
            'subcategory_uuid' => 'required|string',
            'name' => 'required|string',
            'description' => 'required|string',
            'learner_accesibility' => 'required|in:paid,free',
            'status' => 'required|in:Published,Waiting for review,Draft',
        ];

        if($request->test_type != null){
            $validate['test_type'] = 'required|in:classical,IRT,Tes Potensi,TSKKWK';
        }

        if($request->learner_accesibility == 'paid'){
            $validate['price_lifetime'] = 'numeric';
            $validate['price_one_month'] = 'numeric';
            $validate['price_three_months'] = 'numeric';
            $validate['price_six_months'] = 'numeric';
            $validate['price_one_year'] = 'numeric';
            $validate['discount'] = 'numeric';
            $validate['is_membership'] = 'required|boolean';
        }

        // if($request->test_type == 'IRT'){
        //     $validate['max_point'] = 'required|numeric';
        // }

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

        $checkSubcategory = Subcategory::where(['uuid' => $request->subcategory_uuid])->first();
        if(!$checkSubcategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ["subcategory_uuid" => ["Subcategory not found"]],
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
            'subcategory_uuid' => $request->subcategory_uuid,
            'name' => $request->name,
            'description' => $request->description,
            'learner_accesibility' => $request->learner_accesibility,
            'status' => $request->status,
            'image' => $path,
        ];

        if($request->learner_accesibility == 'paid'){
            $validated['price_lifetime'] = $request->price_lifetime ?? 0;
            $validated['price_one_month'] = $request->price_one_month ?? 0;
            $validated['price_three_months'] = $request->price_three_months ?? 0;
            $validated['price_six_months'] = $request->price_six_months ?? 0;
            $validated['price_one_year'] = $request->price_one_year ?? 0;
            $validated['discount'] = $request->discount ?? 0;
            $validated['is_membership'] = $request->is_membership;
        }else{
            $validated['price_lifetime'] = 0;
            $validated['price_one_month'] = 0;
            $validated['price_three_months'] = 0;
            $validated['price_six_months'] = 0;
            $validated['price_one_year'] = 0;
            $validated['discount'] = 0;
            $validated['is_membership'] = 0;
        }

        $validated['test_type'] =  null;
        if($request->test_type){
            $validated['test_type'] = $request->test_type;
        }

        // $validated['max_point'] = null;
        // if($request->test_type == 'IRT'){
        //      $validated['max_point'] = $request->max_point;
        // }

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

        // if($checkPackage->status == 'Published'){
        //     return response()->json([
        //         'message' => 'You can\'t change the package, because this package already published'
        //     ], 422);
        // }

        if($type == 'course'){
            $validate = [
                'courses' => 'required|array',
                'courses.*' => 'required',
                'courses.*.uuid' => 'required',
                'courses.*.course_uuid' => 'required',
                'courses.*.status' => 'required',
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

            foreach ($request->courses as $index => $list) {
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
        }

        $validate = [
            'tests' => 'array',
            'tests.*' => 'required',
            'tests.*.test_uuid' => 'required',
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

        foreach ($request->tests as $index => $list) {
            $checkTryout = Tryout::where([
                'uuid' => $list['test_uuid'],
                'status' => 'Published'
                ])->first();
            if(!$checkTryout){
                return response()->json([
                    'message' => 'Tryout not found / not published',
                ], 404);
            }
            $checkList = PackageTest::where('uuid', $list['uuid'])->first();

            if(!$checkList){
                    $newLists[]=[
                        'uuid' => Uuid::uuid4()->toString(),
                        'package_uuid' => $checkPackage->uuid,
                        'test_uuid' => $list['test_uuid'],
                    ];
            }else{
                $listsUuid[] = $list['uuid'];
                $validatedList=[
                    'test_uuid' => $list['test_uuid'],
                ];
                PackageTest::where('uuid', $list['uuid'])->update($validatedList);
            }
        }

        $pauli_uuid = Test::where('title', 'like', '%Pauli%')->pluck('uuid')->toArray();
        $pauli_packagetest_uuid = PackageTest::where('test_uuid', $pauli_uuid)->pluck('uuid')->toArray();
        $listsUuid[] = $pauli_packagetest_uuid;

        PackageTest::where(['package_uuid' => $uuid])
            ->whereNotIn('uuid', $listsUuid)
            ->delete();
        if(count($newLists) > 0){
            PackageTest::insert($newLists);
        }


        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }
}
