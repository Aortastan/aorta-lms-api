<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TestTag;
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
}
