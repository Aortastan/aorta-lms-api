<?php
namespace App\Traits\Package;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use Ramsey\Uuid\Uuid;

use DateTime;
use DateInterval;

trait PackageTrait
{
    public function getAllPackages($by_admin = false){
        try{
            $uuid = "uuid";
            if($by_admin == false){
                $uuid = "package_uuid";
            }
            $packages = DB::table('packages')
                ->select('packages.uuid as '.$uuid, 'categories.name as category_name', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'packages.price_lifetime', 'packages.price_one_month', 'packages.price_three_months', 'packages.price_six_months','packages.price_one_year', 'packages.learner_accesibility', 'packages.discount', 'packages.is_membership')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid');

                if($by_admin == false){
                    $packages = $packages->where('packages.status', 'Published');
                }

                $packages = $packages->get();

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

    public function getOnePackage($byAdmin = false, $uuid="", $package_type=""){
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
                        "package_tests" => [],
                    ];
                    foreach ($getPackage->packageTests as $index => $test) {
                        $package['package_tests'][] = [
                            "test_uuid" => $test->test->uuid,
                            "title" => $test->test->title,
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
                    ->with(['category', 'packageCourses', 'packageCourses.course', 'packageCourses.course.lessons', 'packageCourses.course.lessons.lectures', 'packageCourses.course.instructor', 'packageCourses.course.pretestPosttests'])
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
                        "package_courses" => [],
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

    // insert purchased package
    public function purchasedPackages($transaction_uuid, $user_uuid, $packages){

        $purchasedPackages = [];
        foreach ($packages as $index => $package) {
            $purchasedPackages[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'transaction_uuid' => $transaction_uuid,
                'package_uuid' => $package['package_uuid'],
                'user_uuid' => $user_uuid,
            ];
        }

        if(count($purchasedPackages) > 0){
            PurchasedPackage::insert($purchasedPackages);
        }
    }

    // insert membership package
    public function membershipPackages($transaction_uuid, $user_uuid, $packages){
        $membershipPackages = [];
        $now = new DateTime();
        foreach ($packages as $index => $package) {
            if($package['type_of_purchase'] == 'one month'){
                $now->add(new DateInterval('P1M'));
            }elseif($package['type_of_purchase'] == 'three months'){
                $now->add(new DateInterval('P3M'));
            }elseif($package['type_of_purchase'] == 'six months'){
                $now->add(new DateInterval('P6M'));
            }
            elseif($package['type_of_purchase'] == 'one year'){
                $now->add(new DateInterval('P1Y'));
            }

            $membershipPackages[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'transaction_uuid' => $transaction_uuid,
                'user_uuid' => $user_uuid,
                'package_uuid' => $package['package_uuid'],
                'expired_date' => $now->format('Y-m-d H:i:s'),
            ];

            if(count($membershipPackages) > 0){
                MembershipHistory::insert($membershipPackages);
            }
        }
    }

    public function checkAvailablePackage($uuid){
        $getPackage = Package::where([
            'uuid' => $uuid,
        ])->first();

        return $getPackage;
    }

    public function checkPurchasedPackage($request, $package_uuid, $user_uuid){
        $purchasedPackage = PurchasedPackage::where(['package_uuid' => $package_uuid, 'user_uuid' => $user_uuid])->first();

        if($purchasedPackage){
            return response()->json([
                'message' => "You've already bought this item",
            ], 422);
        }


        return null;
    }
}
