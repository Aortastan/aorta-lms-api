<?php
namespace App\Traits\Test;
use Illuminate\Support\Facades\DB;
use App\Models\TryoutSegmentTest;

trait TestTrait
{
    public function getTests($search = "", $test_type = "", $type = "", $status = "", $test_category = "", $orderBy = "", $order = ""){
        try{
            $tests = DB::table('tests')
                ->select(
                    'tests.uuid',
                    'tests.test_type',
                    'tests.title',
                    'tests.status',
                    'tests.test_category'
                );

            if($search != null){
                $tests->where('tests.title', 'LIKE', '%'.$search.'%');
            }

            if($test_type != null){
                $tests->where('tests.test_type', $test_type);
            }

            if($type != null){
                $tests->where('tests.type', $type);
            }

            if($status != null){
                $tests->where('tests.status', $status);
            }

            if($test_category != null){
                $tests->where('tests.test_category', $test_category);
            }

            if($orderBy != null && $order != null){
                $orderByArray = ['test_type', 'type', 'title', 'status', 'test_category'];
                $orderArray = ['asc', 'desc'];

                if(in_array($orderBy, $orderByArray) && in_array($order, $orderArray)){
                    $tests->orderBy('tests.' . $orderBy, $order);
                }
            }

            $tests = $tests->get();

            foreach ($tests as $index => $test) {
                $check_tryout_segment_test = TryoutSegmentTest::where([
                    'test_uuid' => $test->uuid,
                ])->first();

                if($check_tryout_segment_test){
                    $test->deletalbe = false;
                }else{
                    $test->deletalbe = true;
                }
            }

            return response()->json([
                'message' => 'Success get data',
                'tests' => $tests,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
