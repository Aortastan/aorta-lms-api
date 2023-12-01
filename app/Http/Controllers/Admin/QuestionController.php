<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Question;
use App\Models\QuestionTest;
use App\Models\Answer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;
use File;
use Illuminate\Support\Str;

use App\Traits\Admin\Question\CreateUpdateQuestionTrait;
use App\Traits\Admin\Question\QuestionValidationRuleTrait;
use App\Traits\SubjectValidationTrait;
use App\Traits\Question\QuestionTrait;

class QuestionController extends Controller
{
    use SubjectValidationTrait, QuestionValidationRuleTrait, CreateUpdateQuestionTrait, QuestionTrait;

    public function index(Request $request){
        $search = "";
        $question_type = "";
        $type = "";
        $status = "";
        $orderBy = "";
        $order = "";
        $subject_uuid = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['question_type'])){
            $question_type = $_GET['question_type'];
        }

        if(isset($_GET['type'])){
            $type = $_GET['type'];
        }

        if(isset($_GET['status'])){
            $status = $_GET['status'];
        }

        if(isset($_GET['subject_uuid'])){
            $subject_uuid = $_GET['subject_uuid'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getQuestions($search, $question_type, $type, $status, $orderBy, $order, $subject_uuid);
    }

    public function published(Request $request){
        $search = "";
        $question_type = "";
        $type = "";
        $status = "Published";
        $orderBy = "";
        $order = "";
        $subject_uuid = "";

        if(isset($_GET['search'])){
            $search = $_GET['search'];
        }

        if(isset($_GET['question_type'])){
            $question_type = $_GET['question_type'];
        }

        if(isset($_GET['type'])){
            $type = $_GET['type'];
        }

        if(isset($_GET['subject_uuid'])){
            $subject_uuid = $_GET['subject_uuid'];
        }

        if(isset($_GET['orderBy']) && isset($_GET['order'])){
            $orderBy = $_GET['orderBy'];
            $order = $_GET['order'];
        }

        return $this->getQuestions($search, $question_type, $type, $status, $orderBy, $order, $subject_uuid);
    }

    public function store(Request $request){
        // validasi ada di traits
        $validator = $this->validateRule($request, 'create');
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validasi subject
        $subjectValidation = $this->validateSubject($request->subject_uuid);
        if ($subjectValidation !== null) {
            return $subjectValidation;
        }

        $this->createQuestion($request);

        return response()->json([
            'message' => 'Success post data',
        ], 200);
    }

    public function update(Request $request, $uuid){
        $question = Question::where('uuid', $uuid)->with(['answers'])->first();
        if(!$question){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        if($question->status == 'Published'){
            $checkQuestion = QuestionTest::where([
                'question_uuid' => $question->uuid,
            ])->first();

            if($checkQuestion){
                return response()->json([
                'message' => 'Cannot change status, because this question is already used in test',
                ]);
            }
        }

        // validasi ada di traits
        $validator = $this->validateRule($request, 'update');

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validasi subject
        $subjectValidation = $this->validateSubject($request->subject_uuid);
        if ($subjectValidation !== null) {
            return $subjectValidation;
        }

        $this->updateQuestion($request, $question);

        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }

    public function duplicate(Request $request){
        $question = Question::where('uuid', $request->question_uuid)->with(['answers'])->first();
        if(!$question){
            return response()->json([
                'message' => 'Data duplicate not found',
            ], 404);
        }

        // validasi ada di traits
        $validator = $this->validateRule($request, 'update');
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validasi subject
        $subjectValidation = $this->validateSubject($request->subject_uuid);
        if ($subjectValidation !== null) {
            return $subjectValidation;
        }

        $this->duplicateQuestion($request, $question);

        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }

    public function show(Request $request, $detail){
        try{
            $getQuestion = Question::where(['uuid' => $detail])->with(['answers'])->first();

            if($getQuestion == null){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
            }

            $getSubject = Subject::where([
                'uuid' => $getQuestion->subject_uuid,
            ])->first();
            $getAuthor = User::where([
                'uuid' => $getQuestion->author_uuid,
            ])->first();

            $answers = [];

            foreach ($getQuestion->answers as $index => $answer) {
                $have_image = 0;
                if($answer['image']){
                    $have_image = 1;
                }
                $answers[] = [
                    'uuid' => $answer['uuid'],
                    'answer' => $answer['answer'],
                    'is_correct' => $answer['is_correct'],
                    'point' => $answer['point'],
                    'have_image' => $have_image,
                    'image' => $answer['image'],
                ];
            }

            $question = [
                'uuid' => $getQuestion->uuid,
                'question_type' => $getQuestion->question_type,
                'title' => $getQuestion->title,
                'question' => $getQuestion->question,
                'subject_name' => $getSubject->name,
                'author_name' => $getAuthor->author_name,
                'file_path' => $getQuestion->file_path,
                'url_path' => $getQuestion->url_path,
                'file_size' => $getQuestion->file_size,
                'file_duration' => $getQuestion->file_duration,
                'type' => $getQuestion->type,
                'status' => $getQuestion->status,
                'different_point' => $getQuestion->different_point,
                'point' => $getQuestion->point,
                'hint' => $getQuestion->hint,
                'answers' => $getQuestion->answers,
            ];

            return response()->json([
                'message' => 'Success get data',
                'questions' => $question,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function delete(Request $request, $uuid){
        $question = Question::where('uuid', $uuid)->first();
        if($question == null){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $checkQuestionTest = QuestionTest::where([
            'question_uuid' => $question->uuid,
        ])->first();

        if($checkQuestionTest){
            return response()->json([
                'message' => 'This question has published and used in tests. You can\'t delete it',
            ], 200);
        }
        Question::where(['uuid' => $uuid])->delete();
        Answer::where(['question_uuid' => $uuid])->delete();
        return response()->json([
            'message' => "Success delete data",
        ], 200);
    }

    public function uploadCSV(Request $request){
        $validate = [
            'file' => 'required|file|mimes:csv,txt',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Membaca isi file CSV
        $file = fopen($request->file, 'r');
        fgetcsv($file);
        // Mendefinisikan array untuk menyimpan data
        $questions = [];
        $answers = [];

        // Membaca baris-baris file CSV
        while (($line = fgetcsv($file)) !== false) {

            // validasi
            if($line[0] != 'multiple' && $line[0] != 'most point'){
                return response()->json([
                    'message' => 'Question type must multiple or most point',
                ], 422);
            }
            if ($line[2] == null){
                return response()->json([
                    'message' => 'Question required',
                ], 422);
            }
            if (!is_string($line[2])){
                return response()->json([
                    'message' => 'Question must string type',
                ], 422);
            }
            if($line[4] != 'text' && $line[4] != 'youtube'){
                return response()->json([
                    'message' => 'Only text / youtube allowed',
                ], 422);
            }

            if($line[4] == 'youtube'){
                if ($line[3] == null){
                    return response()->json([
                        'message' => 'If type is youtube, url_path is required',
                    ], 422);
                }
            }
            $check_subject = Subject::where([
                'name' => $line[1],
            ])->first();

            if(!$check_subject){
                return response()->json([
                    'message' => 'Subject not found',
                ], 422);
            }

            $question_uuid = Uuid::uuid4()->toString();
            // Membuat array asosiatif untuk setiap baris data
            $questionData = [
                "uuid" => $question_uuid,
                "subject_uuid" => $check_subject->uuid,
                'question_type' => $line[0],
                'question' => $line[2],
                'url_path' => $line[3],
                'type' => $line[4],
            ];

            $questions[] = $questionData;

            // Menambahkan setiap jawaban ke dalam array answers
            for ($i = 0; $i < (count($line) - 5) / 3; $i++) {
                $answerIndex = 5 + ($i * 3);

                if ($line[$answerIndex] == null && $line[$answerIndex + 1] == null && $line[$answerIndex + 2] == null){
                    continue;
                }

                if ($line[$answerIndex] == null){
                    return response()->json([
                        'message' => 'Answer is required',
                    ], 422);
                }
                if (!is_string($line[$answerIndex])){
                    return response()->json([
                        'message' => 'Answer must string type',
                    ], 422);
                }
                if ($line[$answerIndex + 1] == null){
                    return response()->json([
                        'message' => 'is_correct is required',
                    ], 422);
                }
                if ($line[$answerIndex + 1] != "0" && $line[$answerIndex + 1] != "1"){
                    return response()->json([
                        'message' => 'is_correct must boolean type',
                    ], 422);
                }
                if ($line[$answerIndex + 2] == null){
                    return response()->json([
                        'message' => 'Point is required',
                    ], 422);
                }
                if (!is_numeric($line[$answerIndex + 2])){
                    return response()->json([
                        'message' => 'Point must number type',
                    ], 422);
                }

                $answerData = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'question_uuid' => $question_uuid,
                    'answer' => $line[$answerIndex],
                    'is_correct' => $line[$answerIndex + 1],
                    'point' => $line[$answerIndex + 2],
                ];
                $answers[] = $answerData;
            }
        }

        // Menutup file CSV
        fclose($file);

        Question::insert($questions);
        Answer::insert($answers);

        return response()->json([
            'message' => 'Success post data',
        ], 200);
    }
}
