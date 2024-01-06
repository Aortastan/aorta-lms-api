<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Test;
use App\Models\Question;
use App\Models\QuestionTest;
use App\Models\SessionTest;
use App\Models\Tag;
use App\Models\StudentQuiz;
use App\Models\LessonQuiz;
use App\Models\StudentTryout;
use App\Models\PackageTest;
use App\Models\PretestPosttest;
use App\Models\StudentPretestPosttest;
use App\Models\TestTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;

use App\Traits\Admin\Test\DuplicateTrait;
use App\Traits\Test\TestTrait;

class TestController extends Controller
{
    use DuplicateTrait, TestTrait;

    public function index(){
        $search = "";
        $test_type = "";
        $type = "";
        $status = "";
        $test_category = "";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['test_type'])){
            $test_type = $_GET['test_type'];
        }

        if(isset($_GET['type'])){
            $type = $_GET['type'];
        }

        if(isset($_GET['status'])){
            $status = $_GET['status'];
        }

        if(isset($_GET['test_category'])){
            $test_category = $_GET['test_category'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getTests($search, $test_type, $type, $status, $test_category, $orderBy, $order);
    }

    public function published(){
        $search = "";
        $test_type = "";
        $type = "";
        $status = "Published";
        $test_category = "";
        $orderBy = "";
        $order = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['test_type'])){
            $test_type = $_GET['test_type'];
        }

        if(isset($_GET['type'])){
            $type = $_GET['type'];
        }

        if(isset($_GET['test_category'])){
            $test_category = $_GET['test_category'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getTests($search, $test_type, $type, $status, $test_category, $orderBy, $order);
    }

    public function show(Request $request, $uuid){
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

    public function preview(Request $request, $uuid){
        $test = Test::select('uuid', 'test_type', 'title', 'status', 'test_category')
        ->where([
            'uuid' => $uuid
        ])->with(['questions.question', 'questions.question.answers'])->first();

        if(!$test){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $getQuestion = [];
        foreach ($test->questions as $key => $data) {
            $answers = [];
            foreach ($data['question']['answers'] as $index => $answer) {
                $answers[] = [
                    'answer_uuid' => $answer['uuid'],
                    'answer' => $answer['answer'],
                    'image' => $answer['image'],
                    'is_correct' => $answer['is_correct'],
                    'answer_correct_explanation' => $answer['answer_correct_explanation'],
                    'point' => $answer['point'],
                    'is_selected' => 0,
                ];
            }
            $getQuestion[] = [
                'question_uuid' => $data['question']['uuid'],
                'status' => $data['question']['status'],
                'title' => $data['question']['title'],
                'question_type' => $data['question']['question_type'],
                'question' => $data['question']['question'],
                'file_path' => $data['question']['file_path'],
                'url_path' => $data['question']['url_path'],
                'type' => $data['question']['type'],
                'hint' => $data['question']['hint'],
                'answers' => $answers,
            ];
        }

        $test = [
            'uuid' => $test['uuid'],
            'test_type' => $test['test_type'],
            'title' => $test['title'],
            'status' => $test['status'],
            'test_category' => $test['test_category'],
            'questions' => $getQuestion,
        ];

        return response()->json([
            'message' => "Success get data",
            'question' => $test,
        ], 200);
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
        foreach($newQuestions as $new) {
            QuestionTest::where('test_uuid', $new['test_uuid'])->delete();
        }

        if(count($newQuestions) > 0){
            QuestionTest::insert($newQuestions);
        }

        $this->updateSessionQuestions($test);
        $this->updateLessonQuizQuestionsStudent($test);
        $this->updatePretestPosttestQuestionsStudent($test);
        $this->updateTryoutQuestionsStudent($test);

        return response()->json([
            'message' => 'Success update questions'
        ], 200);
    }

    public function updateSessionQuestions($test){
        // update session
        $question_tests = QuestionTest::where([
            'test_uuid' => $test['uuid']
        ])->get();


        $sessions = SessionTest::where([
            'test_uuid' => $test->uuid
        ])->get();

        foreach ($sessions as $key => $session) {
            $student_session = [];
            $data_question = json_decode($session->data_question);

            foreach ($question_tests as $data) {
                $question_uuid = $data->question_uuid;
                $answer_uuid = [];
                $status = "";

                foreach ($data_question as $key1 => $data1) {
                    // Melakukan pengecekan apakah $question_uuid ada dalam $data_question
                    if ($data1->question_uuid == $question_uuid) {
                        $answer_uuid = $data1->answer_uuid;
                        $status = $data1->status;
                        break; // Keluar dari loop jika question_uuid ditemukan
                    }
                }

                // Menyimpan data ke dalam $student_session
                $student_session[] = [
                    'question_uuid' => $question_uuid,
                    'answer_uuid' => $answer_uuid,
                    'status' => $status,
                ];
            }

            SessionTest::where([
                'uuid' => $session->uuid
            ])->update([
                'data_question' => json_encode($student_session),
            ]);
        }
    }

    public function updateLessonQuizQuestionsStudent($test){
        // update session
        $question_tests = QuestionTest::where([
            'test_uuid' => $test['uuid']
        ])->get();

        $lesson_quizzes = LessonQuiz::where([
            'test_uuid' => $test->uuid
        ])->get();

        foreach ($lesson_quizzes as $index => $lesson_quiz) {
            $student_quiz = StudentQuiz::where([
                'lesson_quiz_uuid' => $lesson_quiz->uuid,
            ])->get();

            foreach ($student_quiz as $key => $session) {
                $student_session = [];
                $data_question = json_decode($session->data_question);

                foreach ($question_tests as $data) {
                    $question_uuid = $data->question_uuid;
                    $answers = [];
                    $status = "";

                    foreach ($data_question as $key1 => $data1) {
                        // Melakukan pengecekan apakah $question_uuid ada dalam $data_question
                        if ($data1->question_uuid == $question_uuid) {
                            $answers = $data1->answers;
                            break; // Keluar dari loop jika question_uuid ditemukan
                        }
                    }

                    // Menyimpan data ke dalam $student_session
                    $student_session[] = [
                        'question_uuid' => $question_uuid,
                        'answers' => $answers,
                    ];
                }

                StudentQuiz::where([
                    'uuid' => $student_quiz->uuid
                ])->update([
                    'data_question' => json_encode($student_session),
                ]);
            }
        }
    }

    public function updatePretestPosttestQuestionsStudent($test){
        // update session
        $question_tests = QuestionTest::where([
            'test_uuid' => $test['uuid']
        ])->get();

        $pretest_posttests = PretestPosttest::where([
            'test_uuid' => $test->uuid
        ])->get();

        foreach ($pretest_posttests as $index => $pretest_posttest) {
            $student_pretest_posttest = StudentPretestPosttest::where([
                'pretest_posttest_uuid' => $pretest_posttest->uuid,
            ])->get();

            foreach ($student_pretest_posttest as $key => $session) {
                $student_session = [];
                $data_question = json_decode($session->data_question);

                foreach ($question_tests as $data) {
                    $question_uuid = $data->question_uuid;
                    $answers = [];
                    $status = "";

                    foreach ($data_question as $key1 => $data1) {
                        // Melakukan pengecekan apakah $question_uuid ada dalam $data_question
                        if ($data1->question_uuid == $question_uuid) {
                            $answers = $data1->answers;
                            break; // Keluar dari loop jika question_uuid ditemukan
                        }
                    }

                    // Menyimpan data ke dalam $student_session
                    $student_session[] = [
                        'question_uuid' => $question_uuid,
                        'answers' => $answers,
                    ];
                }

                StudentPretestPosttest::where([
                    'uuid' => $session->uuid
                ])->update([
                    'data_question' => json_encode($student_session),
                ]);

            }
        }

    }

    public function updateTryoutQuestionsStudent($test){
        $question_tests = QuestionTest::where([
            'test_uuid' => $test['uuid']
        ])->get();

        $package_tests = PackageTest::where([
            'test_uuid' => $test->uuid
        ])->get();

        foreach ($package_tests as $index => $package_test) {
            $student_tryout = StudentTryout::where([
                'package_test_uuid' => $package_test->uuid,
            ])->get();

            foreach ($student_tryout as $key => $session) {
                $student_session = [];
                $data_question = json_decode($session->data_question);

                foreach ($question_tests as $data) {
                    $question_uuid = $data->question_uuid;
                    $answers = [];
                    $status = "";

                    foreach ($data_question as $key1 => $data1) {
                        // Melakukan pengecekan apakah $question_uuid ada dalam $data_question
                        if ($data1->question_uuid == $question_uuid) {
                            $answers = $data1->answers;
                            break; // Keluar dari loop jika question_uuid ditemukan
                        }
                    }

                    // Menyimpan data ke dalam $student_session
                    $student_session[] = [
                        'question_uuid' => $question_uuid,
                        'answers' => $answers,
                    ];
                }

                StudentTryout::where([
                    'uuid' => $student_tryout->uuid
                ])->update([
                    'data_question' => json_encode($student_session),
                ]);
            }
        }
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
