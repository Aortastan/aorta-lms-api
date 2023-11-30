<?php
namespace App\Traits\Admin\Test;
use App\Models\Test;
use App\Models\QuestionTest;
use App\Models\TestTag;
use Ramsey\Uuid\Uuid;

trait DuplicateTrait
{
    public function duplicateTest($request, $uuid){
        try{
            $test = Test::where(['uuid' => $uuid])->with(['questions', 'tags'])->first();
            $cleaned_data = [
                "test_type" => $test->test_type,
                'title' => $request->title,
                'status' => 'Draft',
                'test_category' => $test->test_category,
            ];
            $new_test = $this->storeTest($cleaned_data);
            $this->duplicateQuestions($new_test->uuid, $test->questions);
            $this->duplicateTestTags($new_test->uuid, $test->tags);

            return response()->json([
                'message' => 'Success duplicate test',
            ]);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function storeTest($cleaned_data){
        try{
            $test = Test::create($cleaned_data);
            return $test;
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }

    }

    private function duplicateQuestions($test_uuid, $questions){
        try{
            $allQuestions=[];
            foreach ($questions as $index => $question) {
                $allQuestions[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'test_uuid' => $test_uuid,
                    'question_uuid' => $question->question_uuid,
                ];
            }

            if(count($allQuestions) > 0){
                QuestionTest::insert($allQuestions);
            }
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    private function duplicateTestTags($test_uuid, $tags){
        try{
            $allTags=[];
            foreach ($tags as $index => $tag) {
                $allTags[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'test_uuid' => $test_uuid,
                    'tag_uuid' => $tag->tag_uuid,
                ];
            }

            if(count($allTags) > 0){
                TestTag::insert($allTags);
            }
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }

    }
}
