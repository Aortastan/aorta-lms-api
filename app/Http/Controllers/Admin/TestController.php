<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\Question;
use App\Models\QuestionTest;
use App\Models\Tag;
use App\Models\TestTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;

use App\Traits\Admin\Test\DuplicateTrait;

class TestController extends Controller
{
    use DuplicateTrait;

    public function index(){
        try{
            $tests = DB::table('tests')
                ->select('tests.uuid', 'tests.test_type', 'tests.title','tests.status', 'tests.test_category')
                ->get();

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

    public function show(Request $request, $uuid){
        try{
            if($uuid == "quiz" || $uuid == "tryout"){
                $tests = Test::where([
                    'test_category' => $uuid
                ])->with(['questions'])->get();

                return response()->json([
                    'message' => 'Success get data',
                    'tests' => $tests,
                ], 200);
            }else{
                $test = Test::select('uuid', 'test_type', 'title', 'status', 'test_category')
                ->where([
                    'uuid' => $uuid
                ])->with(['questions.question.subject'])->first();

                if(!$test){
                    return response()->json([
                        'message' => 'Data not found',
                    ], 404);
                }

                $getQuestion = [];
                foreach ($test->questions as $key => $data) {
                    $getQuestion[] = [
                        'question_uuid' => $data['question']['uuid'],
                        'question_type' => $data['question']['question_type'],
                        'title' => $data['question']['title'],
                        'subject' => $data['question']['subject']['name'],
                        'type' => $data['question']['type'],
                        'question' => $data['question']['question'],
                        'file_path' => $data['question']['file_path'],
                        'url_path' => $data['question']['url_path'],
                        'file_size' => $data['question']['file_size'] . " MB",
                        'file_duration' => $data['question']['file_duration'],
                    ];
                }

                $response_test = [
                    'uuid' => $test['uuid'],
                    'test_type' => $test['test_type'],
                    'title' => $test['title'],
                    'status' => $test['status'],
                    'test_category' => $test['test_category'],
                    'questions' => $getQuestion,
                ];



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


                $response_test['test_tags'] = $testTags;

                return response()->json([
                    'message' => 'Success get data',
                    'test' => $response_test,
                ], 200);
            }
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse{
        $validate = [
            'test_type' => 'required|in:classical,IRT',
            'title' => 'required|string',
            'test_category' => 'required|in:quiz,tryout',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'test_type' => $request->test_type,
            'title' => $request->title,
            'status' => 'Draft',
            'test_category' => $request->test_category,
        ];

        Test::create($validated);

        return response()->json([
            'message' => 'Success create new test'
        ], 200);
    }

    public function duplicate(Request $request, $uuid){
        $test = Test::where(['uuid' => $uuid])->first();
        if(!$test){
            return response()->json([
                'message' => 'Test not found',
            ], 404);
        }

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

        return $this->duplicateTest($request, $uuid);
    }

    public function addQuestions(Request $request, $uuid): JsonResponse{
        $test = Test::where(['uuid' => $uuid])->first();
        if(!$test){
            return response()->json([
                'message' => 'Test not found',
            ], 404);
        }

        if($test->status == "Published"){
            return response()->json([
                'message' => 'Test already published, you cannot edit this test.',
            ], 422);
        }

        $validate = [
            'questions' => 'required|array',
            'questions.*' => 'required',
            'questions.*.uuid' => 'required',
            'questions.*.question_uuid' => 'required',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newQuestions = [];
        $allQuestionsUuid = [];
        foreach ($request->questions as $index => $question) {
            $checkQuestion = Question::where('uuid', $question['question_uuid'])->first();
            if(!$checkQuestion){
                return response()->json([
                    'message' => 'Question not found'
                ], 404);
            }

            $checkQuestionTest = QuestionTest::where('uuid', $question['uuid'])->first();

            if(!$checkQuestionTest){
                $newQuestions[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'test_uuid' => $test->uuid,
                    'question_uuid' => $question['question_uuid'],
                ];
            }else{
                $allQuestionsUuid[] = $checkQuestionTest->uuid;
            }
        }

        QuestionTest::whereNotIn('uuid', $allQuestionsUuid)->delete();

        if(count($newQuestions) > 0){
            QuestionTest::insert($newQuestions);
        }

        return response()->json([
            'message' => 'Success update questions'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $test = Test::where(['uuid' => $uuid])->first();
        if(!$test){
            return response()->json([
                'message' => 'Test not found',
            ], 404);
        }

        if($test->status == "Published"){
            return response()->json([
                'message' => 'Test already published, you cannot edit this test.',
            ], 422);
        }

        $validate = [
            'test_type' => 'required|in:classical,IRT',
            'title' => 'required',
            'test_category' => 'required|in:quiz,tryout',
            'status' => 'required|in:Published,Waiting for review,Draft',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        Test::where(['uuid' => $uuid])->update([
            'test_type' => $request->test_type,
            'title' => $request->title,
            'test_category' => $request->test_category,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Success update test'
        ], 200);
    }

    public function updateTag(Request $request, $uuid){
        $checkTest = Test::where(['uuid' => $uuid])->first();
        if(!$checkTest){
            return response()->json([
                'message' => 'Test not found',
            ], 404);
        }
        $validate = [
            'tags' => 'required|array',
            'tags.*.uuid' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $test_tags = [];
        foreach ($request->tags as $index => $tag_uuid) {
            $checkTag = Tag::where([
                'uuid' => $tag_uuid,
            ])->first();

            if(!$checkTag){
                return response()->json([
                    'message' => 'Tag not found',
                ], 404);
            }
            $test_tags[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'test_uuid' => $checkTest->uuid,
                'tag_uuid' => $checkTag->uuid,
            ];
        }

        TestTag::where([
            'test_uuid' => $checkTest->uuid,
        ])->delete();

        if(count($test_tags) > 0){
            TestTag::insert($test_tags);
        }

        return response()->json([
            'message' => 'Success update tag',
        ], 200);

    }
}
