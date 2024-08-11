<?php
namespace App\Traits\Package;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\PurchasedPackage;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\MembershipHistory;
use Ramsey\Uuid\Uuid;

use DateTime;
use DateInterval;

trait PackageTrait
{
    public function getAllPackages($by_admin = false, $request = ""){
        try{
            $uuid = "uuid";
            if($by_admin == false){
                $uuid = "package_uuid";
            }
            $packages = DB::table('packages')
                ->select('packages.uuid as '.$uuid, 'categories.name as category_name', 'subcategories.name as subcategory_name', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'packages.price_lifetime', 'packages.price_one_month', 'packages.price_three_months', 'packages.price_six_months','packages.price_one_year', 'packages.learner_accesibility', 'packages.discount', 'packages.is_membership', 'packages.test_type')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'packages.subcategory_uuid', '=', 'subcategories.uuid');

                if($by_admin == false){
                    $packages = $packages->where('packages.status', 'Published');
                }

                if($request){
                    if ($request->has('package_type')) {
                        $packageType = $request->input('package_type');
                        if($packageType){
                            $packages = $packages->where('packages.package_type', $packageType);
                        }
                    }
                    if ($request->has('category')) {
                        $category_name = $request->input('category');
                        if($category_name){
                            $category = Category::where([
                                'name' => $category_name
                            ])->first();
                            $packages = $packages->where('packages.category_uuid', $category->uuid);
                        }
                    }
                    if ($request->has('subcategory')) {
                        $subcategory_name = $request->input('subcategory');
                        if($subcategory_name){
                            $subcategory = Subcategory::where([
                                'name' => $subcategory_name
                            ])->first();
                            $packages = $packages->where('packages.subcategory_uuid', $subcategory->uuid);
                        }
                    }
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
                    ->with(['category', 'subcategory', 'packageTests', 'packageTests.test', 'packageTests.test.tryoutSegments', 'packageTests.test.tryoutSegments.tryoutSegmentTests', 'packageTests.test.tryoutSegments.tryoutSegmentTests.test'])
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
                        "test_type" => $getPackage->test_type,
                        // "max_point" => $getPackage->max_point,
                        "created_at" => $getPackage->created_at,
                        "updated_at" => $getPackage->updated_at,
                        "category" => $getPackage->category->name,
                        "subcategory" => $getPackage->subcategory->name,
                        "package_tests" => [],
                    ];
                    foreach ($getPackage->packageTests as $index => $test) {
                        $total_segments = 0;
                        $total_segment_test = 0;
                        foreach ($test->test['tryoutSegments'] as $key => $segment) {
                            $total_segments += 1;
                            $total_segment_test += count($segment['tryoutSegmentTests']);
                        }
                        $package['package_tests'][] = [
                            "test_uuid" => $test->test->uuid,
                            "title" => $test->test->title,
                            'total_segments' => $total_segments,
                            'total_segment_test' => $total_segment_test,
                        ];
                    }
                }
            }elseif($package_type == 'course'){
                $getPackage = Package::
                    where('packages.uuid', $uuid)
                    ->with([
                        'category',
                        'subcategory',
                        'packageCourses',
                        'packageCourses.course',
                        'packageCourses.course.lessons',
                        'packageCourses.course.lessons.lectures',
                        'packageCourses.course.instructor',
                        'packageCourses.course.pretestPosttests',
                        'packageTests' => function ($query) {
                            $query->whereDoesntHave('delyn', function ($q) {
                                $q->where('title', 'LIKE', '%Pauli%')
                                ->where('title', 'LIKE', '%pauli%')
                                ->where('title', 'LIKE', '%Koran%')
                                ->where('title', 'LIKE', '%koran%');
                            });
                        },
                        'packageTests.test'
                    ])
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
                        "subcategory" => $getPackage->subcategory->name,
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

    // cek package mana saja yang dibeli oleh user
    public function checkAllPurchasedPackageByUser($user){
        $purchased_packages = DB::table('purchased_packages')
            ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image')
            ->where('purchased_packages.user_uuid', $user->uuid)
            ->join('packages', 'purchased_packages.package_uuid', '=', 'packages.uuid')
            // ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
            ->distinct('package_uuid')
            ->get();

        $uuid_packages = [];

        foreach ($purchased_packages as $package) {
            $uuid_packages[] = $package->package_uuid;
        }

        return $uuid_packages;
    }

    // cek package mana saja yang dibeli secara membership oleh user, namun dengan pengecualian jika terdapat duplikat dari purchased package, maka yang lebih diutamankan purchased package,
    // not_in_uuid berisi hasil return dari checkAllPurchasedPackageByUser
    public function checkAllMembershipPackageByUser($user, $not_in_uuid){
        $membership_histories = DB::table('membership_histories')
            ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image',  'membership_histories.expired_date')
            ->where('membership_histories.user_uuid', $user->uuid)
            ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
            ->whereNotIn('membership_histories.package_uuid', $not_in_uuid)
            ->whereDate('membership_histories.expired_date', '>', now())
            ->distinct('package_uuid')
            ->get();

        $uuid_packages = [];

        foreach ($membership_histories as $package) {
            $uuid_packages[] = $package->package_uuid;
        }

        return $uuid_packages;
    }
}
