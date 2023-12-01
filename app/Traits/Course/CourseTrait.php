<?php
namespace App\Traits\Course;
use Illuminate\Support\Facades\DB;

trait CourseTrait
{
    public function getCourses($search = "", $status = "", $orderBy = "", $order = ""){
        try{
            $courses = DB::table('courses')
                ->select(
                    'courses.uuid',
                    'courses.title',
                    'courses.description',
                    'courses.image',
                    'courses.video',
                    'courses.number_of_meeting',
                    'courses.is_have_pretest_posttest',
                    'courses.status',
                    'users.name as instructor_name'
                )
                ->join('users', 'courses.instructor_uuid', '=', 'users.uuid');

            if($search != null){
                $courses->where('courses.title', 'LIKE', '%'.$search.'%');
            }

            if($status != null){
                $courses->where('courses.status', $status);
            }

            if($orderBy != null && $order != null){
                $orderByArray = ['title', 'status'];
                $orderArray = ['asc', 'desc'];

                if(in_array($orderBy, $orderByArray) && in_array($order, $orderArray)){
                    $courses->orderBy('courses.' . $orderBy, $order);
                }
            }

            $courses = $courses->get();

            return response()->json([
                'message' => 'Success get data',
                'courses' => $courses,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
