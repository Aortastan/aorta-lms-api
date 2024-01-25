<?php
namespace App\Traits\Question;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionTest;

trait QuestionTrait
{
    public function getQuestions($search = "", $question_type = "", $type = "", $status = "", $orderBy = "", $order = "", $subject_uuid = ""){
        try{
            $questions = DB::table('questions')
            ->select(
                'questions.uuid',
                'questions.title',
                'questions.question_type',
                'questions.question',
                'questions.file_path',
                'questions.url_path',
                'questions.file_size',
                'questions.file_duration',
                'questions.type',
                'questions.status',
                'questions.different_point',
                'questions.point',
                'questions.hint',
                'subjects.name as subject_name',
                'users.name as author_name',
                'users.avatar as author_image')
            ->join('users', 'questions.author_uuid', '=', 'users.uuid')
            ->join('subjects', 'questions.subject_uuid', '=', 'subjects.uuid');

            if($search != null){
                $questions->where('questions.title', 'LIKE', '%'.$search.'%');
            }

            if($question_type != null){
                $questions->where('questions.question_type', $question_type);
            }

            if($type != null){
                $questions->where('questions.type', $type);
            }

            if($status != null){
                $questions->where('questions.status', $status);
            }

            if($subject_uuid != null){
                $questions->where('questions.subject_uuid', $subject_uuid);
            }

            if($orderBy != null && $order != null){
                $orderByArray = ['question_type', 'question', 'type', 'title', 'status'];
                $orderArray = ['asc', 'desc'];

                if(in_array($orderBy, $orderByArray) && in_array($order, $orderArray)){
                    $questions->orderBy('questions.' . $orderBy, $order);
                }
            }

            $questions = $questions->get();

            foreach ($questions as $index => $question) {
                $check_question_test = QuestionTest::where([
                    'question_uuid' => $question->uuid,
                ])->first();

                if($check_question_test){
                    $question->deletalbe = false;
                }else{
                    $question->deletalbe = true;
                }
            }

            return response()->json([
                'message' => 'Success get data',
                'questions' => $questions,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
