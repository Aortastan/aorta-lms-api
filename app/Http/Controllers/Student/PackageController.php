<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;

class PackageController extends Controller
{
    public function index(){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $purchased_packages = DB::table('purchased_packages')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category')
                ->where('purchased_packages.user_uuid', $user->uuid)
                ->join('packages', 'purchased_packages.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->distinct('package_uuid')
                ->get();

            $uuid_packages = [];

            foreach ($purchased_packages as $package) {
                $uuid_packages[] = $package->package_uuid;
            }

            $membership_histories = DB::table('membership_histories')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category', 'membership_histories.expired_date')
                ->where('membership_histories.user_uuid', $user->uuid)
                ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
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
                    'category' => $package->category,
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
                    'category' => $package->category,
                    'expired_date' => $package->expired_date,
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

    public function allPackage(){
        try{
            $packages = DB::table('packages')
                ->select('packages.uuid as package_uuid', 'categories.name as category_name', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'packages.price_lifetime', 'packages.price_one_month', 'packages.price_three_months', 'packages.price_six_months','packages.price_one_year', 'packages.learner_accesibility', 'packages.discount', 'packages.is_membership')
                ->where('packages.status', 1)
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->get();

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

    public function show($package_type, $uuid){
        if($package_type != 'test' && $package_type != 'course'){
            return response()->json([
                'message' => 'Package type not valid',
            ], 404);
        }

        try{
            // $user = JWTAuth::parseToken()->authenticate();
            if($package_type == 'test'){
                $getPackage = Package::
                    where(['uuid' => $uuid, 'package_type' => $package_type])
                    ->with(['category', 'packageTests', 'packageTests.test'])
                    ->first();

                if($getPackage == null){
                    return response()->json([
                        'message' => 'Package not found',
                    ], 404);
                }

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

                $package = [];


                if($getPackage){
                    $package= [
                        "uuid" => $getPackage->uuid,
                        "package_type" => $getPackage->package_type,
                        "name" => $getPackage->name,
                        "description" => $getPackage->description,
                        "image" => $getPackage->image,
                        "category" => $getPackage->category->name,
                        "package_tests" => [],
                    ];
                    foreach ($getPackage->packageTests as $index => $test) {
                        $package['package_tests'][] = [
                            "test_uuid" => $test->test->uuid,
                            "name" => $test->test->name,
                            "test_category" => $test->test->test_category,
                            "attempt" => $test->attempt,
                            "passing_grade" => $test->passing_grade,
                            "duration" => $test->duration,
                        ];
                    }
                }
            }elseif($package_type == 'course'){
                $getPackage = Package::
                    where('packages.uuid', $uuid)
                    ->with(['category', 'packageCourses', 'packageCourses.course', 'packageCourses.course.instructor', 'packageCourses.course.pretestPosttests'])
                    ->first();

                if($getPackage == null){
                    return response()->json([
                        'message' => 'Package not found',
                    ], 404);
                }


                //     $check_purchased_package = DB::table('purchased_packages')
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

                $package = [];

                if($getPackage){
                    $package= [
                        "uuid" => $getPackage->uuid,
                        "package_type" => $getPackage->package_type,
                        "name" => $getPackage->name,
                        "description" => $getPackage->description,
                        "image" => $getPackage->image,
                        "category" => $getPackage->category->name,
                        "package_courses" => [],
                    ];

                    foreach ($getPackage->packageCourses as $index => $course) {
                        $pretestPosttests = [];

                        foreach ($course->course->pretestPosttests as $index1 => $pretestPosttest)  {
                            $pretestPosttests[] = [
                                'pretestpostest_uuid' => $pretestPosttest->uuid,
                                "max_attempt" => $pretestPosttest->max_attempt,
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
                            "pretest_posttests" => $pretestPosttests,
                        ];
                    }
                }
            }


            return response()->json([
                'message' => 'Success get data',
                'package' => $package,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
