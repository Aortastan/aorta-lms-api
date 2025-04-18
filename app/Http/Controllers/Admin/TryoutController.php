<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;
use App\Models\Tryout;
use App\Models\TryoutSegmentTest;
use App\Models\TryoutSegment;
use App\Models\PackageTest;

class TryoutController extends Controller
{
    public function index(){
        $search = "";
        $status = "";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['status'])){
            $status = $_GET['status'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        $tryouts = DB::table('tryouts')
        ->select(
            'tryouts.uuid',
            'tryouts.title',
            'tryouts.status',
        );

        if($search != null){
            $tryouts->where('tryouts.title', 'LIKE', '%'.$search.'%');
        }

        if($status != null){
            $tryouts->where('tryouts.status', $status);
        }

        if($orderBy != null && $order != null){
            $orderByArray = ['title', 'status'];
            $orderArray = ['asc', 'desc'];

            if(in_array($orderBy, $orderByArray) && in_array($order, $orderArray)){
                $tryouts->orderBy('tryouts.' . $orderBy, $order);
            }
        }

        $tryouts = $tryouts->get();

        foreach ($tryouts as $index => $tryout) {
            $check_tryout = PackageTest::where([
                'test_uuid' => $tryout->uuid,
            ])->first();

            if($check_tryout){
                $tryout->deletable = false;
            }else{
                $tryout->deletable = true;
            }
        }

        return response()->json([
            'message' => 'Success get data',
            'tryouts' => $tryouts,
        ], 200);
    }

    public function show(Request $request, $uuid){
        $tryout = Tryout::select('uuid', 'title', 'status')
        ->where([
            'uuid' => $uuid
        ])->with(['tryoutSegments', 'tryoutSegments.tryoutSegmentTests', 'tryoutSegments.tryoutSegmentTests.test'])->first();

        if(!$tryout){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $getSegments = [];
        foreach ($tryout->tryoutSegments as $key => $data) {
            $segmentTests = [];
            foreach ($data['tryoutSegmentTests'] as $key1 => $data1) {
                if($data1['passing_score'] == NULL){
                    $data1['passing_score'] = $data1['test']['passing_score'];
                }
                $segmentTests[] = [
                    'segment_test_uuid' => $data1['uuid'],
                    'test_uuid' => $data1['test_uuid'],
                    'attempt' => $data1['attempt'],
                    'duration' => $data1['duration'],
                    'max_point' => $data1['max_point'],
                    'test_type' => $data1['test']['test_type'],
                    'test_title' => $data1['test']['test_title'],
                    'test_student_title_display' => $data1['test']['student_title_display'],
                    'test_passing_score' => $data1['passing_score'],
                    'test_category' => $data1['test']['test_category'],
                    'test_status' => $data1['test']['status'],
                ];
            }
            $getSegments[] = [
                'segment_uuid' => $data['uuid'],
                'segment_title' => $data['title'],
                'segment_tests' => $segmentTests,
            ];
        }

        $response_tryout = [
            'uuid' => $tryout['uuid'],
            'title' => $tryout['title'],
            'status' => $tryout['status'],
            'tryout_segments' => $getSegments
        ];

        return response()->json([
            'message' => 'Success get data',
            'tryout' => $response_tryout,
        ], 200);
    }

    public function submit(Request $request): JsonResponse{
        $validate = [
            'title' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'title' => $request->title,
            'status' => 'Draft',
        ];

        Tryout::create($validated);

        return response()->json([
            'message' => 'Success create new tryout'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $tryout = Tryout::where(['uuid' => $uuid])->first();
        if(!$tryout){
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        if($tryout->status == "Published"){
            return response()->json([
                'message' => 'tryout already published, you cannot edit this tryout.',
            ], 422);
        }

        $validate = [
            'title' => 'required',
            'status' => 'required|in:Published,Waiting for review,Draft',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        Tryout::where(['uuid' => $uuid])->update([
            'title' => $request->title,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Success update tryout'
        ], 200);
    }

    public function addTests(Request $request, $uuid){
        $tryout = Tryout::where(['uuid' => $uuid])->with(['tryoutSegments'])->first();
        if(!$tryout){
            return response()->json([
                'message' => 'Tryout not found',
            ], 404);
        }

        if($tryout->status == "Published"){
            return response()->json([
                'message' => 'tryout already published, you cannot edit this tryout.',
            ], 422);
        }

        $validate = [
            'segments' => 'required|array',
            'segments.*.title' => 'required',
            'segments.*.tests' => 'required|array',
            'segments.*.tests.*.test_uuid' => 'required',
            'segments.*.tests.*.attempt' => 'required',
            'segments.*.tests.*.duration' => 'required',
            'segments.*.tests.*.max_point' => 'required',
            'segmentes.*.tests.*.test_passing_score' => 'required'
        ];
        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        TryoutSegment::where([
            'tryout_uuid' => $uuid
        ])->delete();
        foreach ($tryout['tryoutSegments'] as $key => $data) {
            TryoutSegmentTest::where([
                'tryout_segment_uuid' => $data['uuid'],
            ])->delete();
        }

        foreach ($request->segments as $key => $segmentData) {
            $segment = TryoutSegment::create([
                'tryout_uuid' => $uuid,
                'title' => $segmentData['title']
            ]);

            foreach ($segmentData['tests'] as $key1 => $test) {
                TryoutSegmentTest::create([
                    'tryout_segment_uuid' => $segment->uuid,
                    'test_uuid' => $test['test_uuid'],
                    'attempt' => $test['attempt'],
                    'duration' => $test['duration'],
                    'max_point' => $test['max_point'],
                    'passing_score' => $test['test_passing_score']
                ]);
            }
        }


        return response()->json([
            'message' => 'Add tests success',
        ], 200);
    }

    public function delete($uuid){
        $check_tryout = Tryout::where([
            'uuid' => $uuid,
        ])->first();

        if($check_tryout == null){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $check_package_test = PackageTest::where([
            'test_uuid' => $uuid,
        ])->first();

        if($check_package_test){
            return response()->json([
                'message' => 'This tryout has published and used in package. You can\'t delete it',
            ], 404);
        }

        $check_tryout_segment = TryoutSegment::where([
            'tryout_uuid' => $uuid,
        ])->get();

        foreach ($check_tryout_segment as $index => $segment) {
            TryoutSegmentTest::where([
                'tryout_segment_uuid' => $segment->uuid,
            ])->delete();
        }

        TryoutSegment::where([
            'tryout_uuid' => $uuid,
        ])->delete();

        return response()->json([
            'message' => 'Delete succesfully',
        ], 200);
    }
}
