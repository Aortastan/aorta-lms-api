<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TestTag;
use App\Models\Test;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\PackageTest;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    public function show($package_uuid, $uuid){
        try{
            $test = DB::table('tests')
                    ->select('tests.uuid', 'tests.title', 'tests.test_type', 'tests.test_category')
                    ->where(['tests.uuid' => $uuid])
                    ->first();

            if($test == null){
                return response()->json([
                    'message' => "Test not found",
                ], 404);
            }

            $getTestTags = TestTag::where([
                'test_uuid' => $test->uuid,
            ])->with(['tag'])->get();

            $testTags = [];
            foreach ($getTestTags as $index => $tag) {
                $testTags[] = [
                    'tag_uuid' => $tag->tag->uuid,
                    'name' => $tag->tag->name,
                ];
            }

            return response()->json([
                'message' => 'Success get data',
                'test' => $test,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function detailPurchasedTest($test_uuid){
        $user = JWTAuth::parseToken()->authenticate();

        return $this->checkThisTestIsPaid($test_uuid, $user);
    }

    public function checkThisTestIsPaid($test_uuid, $user){
        // cek apakah test uuid tersebut ada
        $test = Test::where([
            'uuid' => $test_uuid,
        ])->first();

        if($test == null){
            return response()->json([
                'message' => "Test not found",
            ]);
        }

        // cek package mana aja yang menyimpan Test tersebut
        $check_package_tests = PackageTest::where([
            'test_uuid' => $test->uuid,
        ])->get();

        $package_uuids = [];
        foreach ($check_package_tests as $index => $package) {
            $package_uuids[] = $package->package_uuid;
        }

        if(count($package_uuids) <= 0){
            return response()->json([
                'message' => "Package test not found",
            ]);
        }

        // cek apakah user pernah membeli lifetime package tersebut
        $check_purchased_package = PurchasedPackage::where([
            "user_uuid" => $user->uuid,
        ])->whereIn("package_uuid", $package_uuids)->first();

        // jika ternyata tidak ada, maka sekarang cek di membership
        if($check_purchased_package == null){
            $check_membership_package = MembershipHistory::where([
                "user_uuid" => $user->uuid,
            ])
            ->whereDate('expired_date', '>', now())
            ->whereIn("package_uuid", $package_uuids)->first();

            if($check_membership_package == null){
                return response()->json([
                    'message' => 'You can\'t access this test',
                ]);
            }
        }

        $getTest = Test::where([
            'uuid' => $test_uuid
        ])->with(['questions'])->first();


        $test = [
            "uuid" => $getTest->uuid,
            "title" => $getTest->title,
            "questions" => $getTest->questions,
        ];

        return $test;
    }

    public function getStudentTests(){
        $user = JWTAuth::parseToken()->authenticate();
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

            $get_test_purchased = PackageTest::whereIn('package_uuid', $uuid_packages)->with(['test'])->get();

            $my_tests = [];
            $test_uuids = [];
            foreach ($get_test_purchased as $index => $student_test) {
                if (!in_array($student_test->test_uuid, $test_uuids)) {
                    $test_uuids[] = $student_test->test_uuid;
                    $my_tests[] = [
                        "test_uuid" => $student_test->test_uuid,
                        "type" => "lifetime",
                        "title" => $student_test->test->title,
                        'test_type' => $student_test->test->test_type,
                    ];
                }
            }

            $membership_histories = DB::table('membership_histories')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image',  'membership_histories.expired_date')
                ->where('membership_histories.user_uuid', $user->uuid)
                ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
                ->whereNotIn('membership_histories.package_uuid', $uuid_packages)
                ->whereDate('membership_histories.expired_date', '>', now())
                ->distinct('package_uuid')
                ->get();

            $uuid_packages = [];

            foreach ($membership_histories as $package) {
                $uuid_packages[] = $package->package_uuid;
            }

            $get_course_membership = PackageTest::whereIn('package_uuid', $uuid_packages)->with(['test'])->get();


            foreach ($get_course_membership as $index => $student_test) {
                if (!in_array($student_test->test_uuid, $test_uuids)) {
                    $test_uuids[] = $student_test->test_uuid;
                    $my_tests[] = [
                        "test_uuid" => $student_test->test_uuid,
                        "type" => "lifetime",
                        "title" => $student_test->test->title,
                        'test_type' => $student_test->test->test_type,
                    ];
                }
            }

            return response()->json([
                'message'=> "success get data",
                "tests" => $my_tests,
            ], 200);
    }
}
