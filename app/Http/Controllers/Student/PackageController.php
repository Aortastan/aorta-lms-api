<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;

use App\Traits\Package\PackageTrait;

class PackageController extends Controller
{
    use PackageTrait;
    public function index(Request $request){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $purchased_packages = DB::table('purchased_packages')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image','packages.test_type', 'categories.name as category', 'subcategories.name as subcategory')
                ->where('purchased_packages.user_uuid', $user->uuid)
                ->join('packages', 'purchased_packages.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'packages.subcategory_uuid', '=', 'subcategories.uuid')
                ->distinct('package_uuid')
                ->get();

            $uuid_packages = [];

            foreach ($purchased_packages as $package) {
                $uuid_packages[] = $package->package_uuid;
            }

            $membership_histories = DB::table('membership_histories')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image','packages.test_type',  'categories.name as category', 'subcategories.name as subcategory', 'membership_histories.expired_date')
                ->where('membership_histories.user_uuid', $user->uuid)
                ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'packages.subcategory_uuid', '=', 'subcategories.uuid')
                ->whereNotIn('membership_histories.package_uuid', $uuid_packages)
                ->whereDate('membership_histories.expired_date', '>', now())
                ->distinct('package_uuid')
                ->get();

            $packages = [];
            foreach ($purchased_packages as $index => $package) {
                $packages[] = [
                    'package_uuid' => $package->package_uuid,
                    'package_type' => $package->package_type,
                    'name' => $package->name,
                    'description' => $package->description,
                    'image' => $package->image,
                    'test_type' => $package->test_type,
                    'category' => $package->category,
                    'subcategory' => $package->subcategory,
                    'expired_date' => null,
                ];
            }

            foreach ($membership_histories as $index => $package) {
                $packages[] = [
                    'package_uuid' => $package->package_uuid,
                    'package_type' => $package->package_type,
                    'name' => $package->name,
                    'description' => $package->description,
                    'image' => $package->image,
                    'test_type' => $package->test_type,
                    'category' => $package->category,
                    'subcategory' => $package->subcategory,
                    'expired_date' => $package->expired_date,
                ];
            }

        if ($request->has('package_type')) {
            foreach ($packages as $key => $package) {
                $packageUuid = $package['package_uuid'];
                $exists = DB::table('package_tests')->where('package_uuid', $packageUuid)->exists();
                $packages[$key]['exists_in_tests'] = $exists;
            }
            $package_type = $request->input('package_type');
            if($package_type){
                $testPackages = [];
                foreach ($packages as $package) {
                    if ($package["package_type"] == $package_type) {
                        $testPackages[] = $package;
                    }
                    if($package["package_type"] == 'course' && $package["exists_in_tests"] == true) {
                        $package["package_type"] = "test";
                        $testPackages[] = $package;
                    }
                }
                $packages = $testPackages;
            }
        }

            return response()->json([
                'message' => 'Sukses mengambil data',
                'packages' => $packages,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function showDetailPurchasedPackage($uuid){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $purchased_packages = DB::table('purchased_packages')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category', 'subcategories.name as subcategory')
                ->where('purchased_packages.user_uuid', $user->uuid)
                ->where('purchased_packages.package_uuid', $uuid)
                ->join('packages', 'purchased_packages.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->first();

            $membership_histories = DB::table('membership_histories')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category', 'subcategories.name as subcategory', 'membership_histories.expired_date')
                ->where('membership_histories.user_uuid', $user->uuid)
                ->where('membership_histories.package_uuid', $uuid)
                ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->whereDate('membership_histories.expired_date', '>', now())
                ->first();

            if(!$purchased_packages && !$membership_histories){
                return response()->json([
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            $getPackage = Package::
                    where('packages.uuid', $uuid)
                    ->with(['category', 'subcategory', 'packageCourses', 'packageCourses.course', 'packageCourses.course.lessons', 'packageCourses.course.lessons.lectures', 'packageCourses.course.instructor', 'packageCourses.course.pretestPosttests', 'packageTests', 'packageTests.test'])
                    ->first();

                if($getPackage == null){
                    return response()->json([
                        'message' => 'Package tidak ditemukan',
                    ], 404);
                }

                $package = [];

                if($getPackage){
                    $package= [
                        "uuid" => $getPackage->uuid,
                        "package_type" => $getPackage->package_type,
                        "name" => $getPackage->name,
                        "description" => $getPackage->description,
                        "image" => $getPackage->image,
                        "price_lifetime" => $getPackage->price_lifetime,
                        "price_one_month" => $getPackage->price_one_month,
                        "price_three_months	" => $getPackage->price_three_months	,
                        "price_six_months" => $getPackage->price_six_months,
                        "price_one_year" => $getPackage->price_one_year,
                        "learner_accesibility" => $getPackage->learner_accesibility,
                        "discount" => $getPackage->discount,
                        "is_membership" => $getPackage->is_membership,
                        "status" => $getPackage->status,
                        "created_at" => $getPackage->created_at,
                        "updated_at" => $getPackage->updated_at,
                        "category" => $getPackage->category->name,
                        "subcategory" => $getPackage->subcategory->name,
                        "category_uuid" => $getPackage->category->uuid,
                        "subcategory_uuid" => $getPackage->subcategory->uuid,
                        "package_courses" => [],
                        "package_tests" => [],
                    ];

                    foreach ($getPackage->packageCourses as $index => $course) {
                        $pretestPosttests = [];
                        $lessons = [];

                        foreach ($course->course->pretestPosttests as $index1 => $pretestPosttest)  {
                            $pretestPosttests[] = [
                                'pretestpostest_uuid' => $pretestPosttest->uuid,
                                "max_attempt" => $pretestPosttest->max_attempt,
                            ];
                        }

                        foreach ($course->course->lessons as $index1 => $lesson)  {
                            $lectures = [];
                            foreach ($lesson->lectures as $index2 => $lecture)  {
                                $lectures[] = [
                                    "lecture_uuid" => $lecture->uuid,
                                    "title" => $lecture->title,
                                ];
                            }
                            $lessons[] = [
                                'lesson_uuid' => $lesson->uuid,
                                "title" => $lesson->title,
                                "description" => $lesson->description,
                                "lectures" => $lectures,
                            ];
                        }
                        $package['package_courses'][] = [
                            "course_uuid" => $course->course->uuid,
                            "title" => $course->course->title,
                            "description" => $course->course->description,
                            "image" => $course->course->image,
                            "video" => $course->course->video,
                            "number_of_meeting" => $course->course->number_of_meeting,
                            "instructor_name" => $course->course->instructor->name,
                            "lessons" => $lessons,
                            "pretest_posttests" => $pretestPosttests,
                        ];
                    }

                    foreach ($getPackage->packageTests as $index => $test) {
                        $package['package_tests'][] = [
                            "test_uuid" => $test->test->uuid,
                            "title" => $test->test->title,
                            "test_category" => $test->test->test_category,
                            "attempt" => $test->attempt,
                            "duration" => $test->duration,
                        ];
                    }
                }

            return response()->json([
                'message' => 'Sukses mengambil data',
                'package' => $package,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function allPackage(Request $request){
        return $this->getAllPackages(false, $request);
    }

    public function show($package_type, $uuid){
        if($package_type != 'test' && $package_type != 'course'){
            return response()->json([
                'message' => 'Tipe paket tidak valid',
            ], 404);
        }

        return $this->getOnePackage(false, $uuid, $package_type);
    }
}
