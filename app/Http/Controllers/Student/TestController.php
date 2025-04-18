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

use App\Traits\Package\PackageTrait;

class TestController extends Controller
{
    use PackageTrait;

    public function show($package_uuid, $uuid){
        try{
            $test = DB::table('tests')
                    ->select('tests.uuid', 'tests.title', 'tests.test_type', 'tests.test_category')
                    ->where(['tests.uuid' => $uuid])
                    ->first();

            if($test == null){
                return response()->json([
                    'message' => "Tes tidak ditemukan",
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
                'message' => 'Sukses mengambil data',
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
                'message' => "Test tidak ditemukan",
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
                'message' => "Paket tes tidak ditemukan",
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

    // ambil semua test yang pernah dibeli berdasarkan package pacakge
    public function getStudentTests(Request $request){
        $user = JWTAuth::parseToken()->authenticate();
        $my_tests = [];
        if ($request->has('package_uuid')) {
            $packageUuid = $request->input('package_uuid');
            $get_package_test = PackageTest::where('package_uuid', $packageUuid)
            ->whereDoesntHave('delyn', function($query) {
                $query->where('title', 'LIKE', '%Pauli%')
                    ->orWhere('title', 'LIKE', '%pauli%')
                    ->orWhere('title', 'LIKE', '%Koran%')
                    ->orWhere('title', 'LIKE', '%koran%');
            })
            ->with(['test', 'test.tryoutSegments', 'test.tryoutSegments.tryoutSegmentTests', 'test.tryoutSegments.tryoutSegmentTests.test', 'test.tryoutSegments', 'test.tryoutSegments.tryoutSegmentTests', 'test.tryoutSegments.tryoutSegmentTests.test'])
            ->get();

            $my_tests = [];
            $tryout_uuids = [];
            foreach ($get_package_test as $index => $student_test) {
                if (!in_array($student_test->uuid, $tryout_uuids)) {
                    $tryout_uuids[] = $student_test->uuid;
                    $tryout_segments = [];
                    foreach ($student_test->test->tryoutSegments as $key1 => $tryout_segment) {
                        $tryout_segment_tests = [];
                        foreach ($tryout_segment['tryoutSegmentTests'] as $key => $tryoutSegmentTests) {
                            $title_test = $tryoutSegmentTests['test']['title'];
                            if($tryoutSegmentTests['test']['student_title_display']){
                                $title_test = $tryoutSegmentTests['test']['student_title_display'];
                            }

                            $tryout_segment_tests[] = [
                                'tryout_segment_tests_uuid' => $tryoutSegmentTests['uuid'],
                                'test_uuid' => $tryoutSegmentTests['test_uuid'],
                                'attempt' => $tryoutSegmentTests['attempt'],
                                'duration' => $tryoutSegmentTests['duration'],
                                'max_point' => $tryoutSegmentTests['max_point'],
                                'title_test' => $title_test,
                            ];
                        }
                        $tryout_segments[] = [
                            'title_segment' => $tryout_segment['title'],
                            'tryout_segment_tests' => $tryout_segment_tests,
                        ];
                    }
                    $my_tests[] = [
                        "uuid" => $student_test->uuid,
                        "package_uuid" => $student_test->package_uuid,
                        "test_uuid" => $student_test->test_uuid,
                        "type" => "Lifetime",
                        "title" => $student_test->test->title,
                        "tryout_segments" => $tryout_segments,
                    ];
                }
            }
        }else{
            $uuid_packages = $this->checkAllPurchasedPackageByUser($user);
            $get_test_purchased = PackageTest::whereIn('package_uuid', $uuid_packages)->with(['test', 'test.tryoutSegments', 'test.tryoutSegments.tryoutSegmentTests', 'test.tryoutSegments.tryoutSegmentTests.test'])->get();

            $my_tests = [];
            $tryout_uuids = [];
            foreach ($get_test_purchased as $index => $student_test) {
                if (!in_array($student_test->uuid, $tryout_uuids)) {
                    $tryout_uuids[] = $student_test->uuid;
                    $tryout_segments = [];
                    foreach ($student_test->test->tryoutSegments as $key1 => $tryout_segment) {
                        $tryout_segment_tests = [];
                        foreach ($tryout_segment['tryoutSegmentTests'] as $key => $tryoutSegmentTests) {

                            $title_test = $tryoutSegmentTests['test']['title'];
                            if($tryoutSegmentTests['test']['student_title_display']){
                                $title_test = $tryoutSegmentTests['test']['student_title_display'];
                            }

                            $tryout_segment_tests[] = [
                                'tryout_segment_tests_uuid' => $tryoutSegmentTests['uuid'],
                                'test_uuid' => $tryoutSegmentTests['test_uuid'],
                                'attempt' => $tryoutSegmentTests['attempt'],
                                'duration' => $tryoutSegmentTests['duration'],
                                'max_point' => $tryoutSegmentTests['max_point'],
                                'title_test' => $title_test,
                            ];
                        }
                        $tryout_segments[] = [
                            'title_segment' => $tryout_segment['title'],
                            'tryout_segment_tests' => $tryout_segment_tests,
                        ];
                    }
                    $my_tests[] = [
                        "package_uuid" => $student_test->package_uuid,
                        "test_uuid" => $student_test->uuid,
                        "type" => "Lifetime",
                        "title" => $student_test->test->title,
                        "tryout_segments" => $tryout_segments,
                    ];
                }
            }

            $uuid_packages = $this->checkAllMembershipPackageByUser($user, $uuid_packages);
            $get_course_membership = PackageTest::whereIn('package_uuid', $uuid_packages)->with(['test', 'test.tryoutSegments', 'test.tryoutSegments.tryoutSegmentTests', 'test.tryoutSegments.tryoutSegmentTests.test'])->get();

            foreach ($get_course_membership as $index => $student_test) {
                if (!in_array($student_test->uuid, $tryout_uuids)) {
                    $tryout_uuids[] = $student_test->uuid;
                    $tryout_segments = [];
                    foreach ($student_test->test->tryoutSegments as $key1 => $tryout_segment) {

                        $title_test = $tryoutSegmentTests['test']['title'];
                        if($tryoutSegmentTests['test']['student_title_display']){
                            $title_test = $tryoutSegmentTests['test']['student_title_display'];
                        }

                        $tryout_segment_tests = [];
                        foreach ($tryout_segment['tryoutSegmentTests'] as $key => $tryoutSegmentTests) {
                            $tryout_segment_tests[] = [
                                'tryout_segment_tests_uuid' => $tryoutSegmentTests['uuid'],
                                'attempt' => $tryoutSegmentTests['attempt'],
                                'duration' => $tryoutSegmentTests['duration'],
                                'max_point' => $tryoutSegmentTests['max_point'],
                                'title_test' => $title_test,
                            ];
                        }
                        $tryout_segments[] = [
                            'title_segment' => $tryout_segment['title'],
                            'tryout_segment_tests' => $tryout_segment_tests,
                        ];
                    }
                    $my_tests[] = [
                        "uuid" => $student_test->uuid,
                        "package_uuid" => $student_test->package_uuid,
                        "test_uuid" => $student_test->test_uuid,
                        "type" => "Membership",
                        "title" => $student_test->test->title,
                        "tryout_segments" => $tryout_segments,
                    ];
                }
            }
        }


        return response()->json([
            'message'=> "Sukses mengambil data",
            "tests" => $my_tests,
        ], 200);
    }
}
