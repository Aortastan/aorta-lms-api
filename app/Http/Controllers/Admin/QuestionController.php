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

class QuestionController extends Controller
{
    public function index(Request $request){
        try{

            $search = "";

            $questions = DB::table('questions')->select('questions.uuid', 'questions.title', 'questions.question_type', 'questions.question', 'questions.file_path', 'questions.url_path', 'questions.file_size', 'questions.file_duration', 'questions.type', 'questions.status', 'subjects.name as subject_name', 'users.name as author_name', 'users.avatar as author_image')
                ->join('users', 'questions.author_uuid', '=', 'users.uuid')
                ->join('subjects', 'questions.subject_uuid', '=', 'subjects.uuid');

            if(isset($_GET['search'])){
                $questions->where('questions.title', 'LIKE', '%'.$_GET['search'].'%');
            }

            if(isset($_GET['question_type'])){
                $questions->where('questions.question_type', $_GET['question_type']);
            }

            if(isset($_GET['type'])){
                $questions->where('questions.type', $_GET['type']);
            }

            if(isset($_GET['status'])){
                $questions->where('questions.status', $_GET['status']);
            }

            if(isset($_GET['orderBy']) && isset($_GET['order'])){
                $orderBy = ['question_type', 'question', 'type', 'title', 'status'];
                $order = ['asc', 'desc'];

                if(in_array($_GET['orderBy'], $orderBy) && in_array($_GET['order'], $order)){
                    $questions->orderBy('questions.' . $_GET['orderBy'], $_GET['order']);
                }
            }

            $questions = $questions->get();

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

    public function store(Request $request){
        $validate = [
            'subject_uuid' => 'required|string',
            'title' => 'required|string',
            'question' => 'required|string',
            'question_type' => 'required|in:multi choice,most point,single choice,fill in blank,true false',
            'type' => 'required|in:video,youtube,text,image,pdf,audio,slide document',
            'status' => 'required|in:Published,Waiting for review,Draft',
            'different_point' => 'required|in:1,0',
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.answer' => 'required|string',
        ];

        if($request->type){
            if($request->type != 'text'){
                if($request->type == 'youtube'){
                    $validate['url_path'] = 'required';
                    $validate['file_duration'] = 'required';
                }else{
                    if($request->type == 'video' || $request->type == 'audio'){
                        $validate['file_duration'] = 'required';
                    }
                    if($request->type == 'pdf'){
                        $validate['file'] = "required|mimes:pdf";
                    }elseif($request->type == 'video'){
                        $validate['file'] = "required|mimes:mp4,avi,mov,wmv";
                    }elseif($request->type == 'audio'){
                        $validate['file'] = "required|mimes:mp3,wav,ogg";
                    }elseif($request->type == 'image'){
                        $validate['file'] = "required|mimes:jpeg,png,jpg,gif";
                    }elseif($request->type == 'slide document'){
                        $validate['file'] = "required|mimes:ppt,pptx";
                    }
                }
            }
        }

        if($request->hint){
            $validate['hint'] = 'required|string';
        }

        if($request->question_type != 'fill in blank'){
            $validate['answers.*.is_correct'] = 'required|in:1,0';
            $validate['answers.*.have_image'] = 'required|in:1,0';
        }

        if($request->different_point == 1){
            $validate['answers.*.point'] = 'required|integer';
        }else{
            $validate['point'] = 'required|integer';
        }


        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkSubject = Subject::where([
            'uuid' => $request->subject_uuid,
        ])->first();

        if(!$checkSubject){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'subject_uuid' => [
                        "Subject not found",
                    ],
                ],
            ], 422);
        }

        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;

        if($request->type != 'text'){
            if($request->type == 'youtube'){
                $url_path = $request->url_path;
            }else{
                $file_size = $request->file->getSize();
                $path = $request->file->store('questions', 'public');
                $file_size = round($file_size / (1024 * 1024), 2); //Megabytes
                $file_duration = $request->file_duration;
            }
        }

        $point = null;
        if($request->different_point == 0){
            $point = $request->point;
        }

        $user = JWTAuth::parseToken()->authenticate();

        $validated=[
            'author_uuid' => $user->uuid,
            'subject_uuid' => $request->subject_uuid,
            'title' => $request->title,
            'question_type' => $request->question_type,
            'question' => $request->question,
            'file_path' => $path,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'type' => $request->type,
            'different_point' => $request->different_point,
            'point' => $point,
            'hint' => $request->hint,
            'status' => $request->status,
        ];

        $question = Question::create($validated);

        $answers = [];
        foreach ($request->answers as $index => $answer) {
            $path = null;
            if($answer['have_image'] == 1){
                if($answer['image']){
                    $mime = $answer['image']->getMimeType();
                    // Pengecekan apakah tipe MIME adalah tipe gambar
                    if (Str::startsWith($mime, 'image/')) {
                        // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                        $path = $answer['image']->store('imagesAnswer', 'public');
                    }
                }
            }
            $point = null;
            $is_correct = null;

            if($request->question_type == 'most point'){
                $is_correct = 1;
                $point = $answer['point'];
            }else{
                $is_correct = $answer['is_correct'];
                if($request->different_point == 1){
                    $point = $answer['point'];
                }
            }

            $answers[]=[
                'uuid' => Uuid::uuid4()->toString(),
                'question_uuid' => $question->uuid,
                'answer' => $answer['answer'],
                'image' => $path,
                'is_correct' => $is_correct,
                'point' => $point,
            ];
        }

        Answer::insert($answers);

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

        $validate = [
            'subject_uuid' => 'required|string',
            'title' => 'required|string',
            'question' => 'required|string',
            'question_type' => 'required|in:multi choice,most point,single choice,fill in blank,true false',
            'type' => 'required|in:video,youtube,text,image,pdf,audio,slide document',
            'status' => 'required|in:Published,Waiting for review,Draft',
            'different_point' => 'required|in:1,0',
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.answer' => 'required|string',
        ];

        if($request->type){
            if($request->type != 'text'){
                if($request->type == 'youtube'){
                    $validate['url_path'] = 'required';
                    $validate['file_duration'] = 'required';
                }else{
                    if($request->type == 'video' || $request->type == 'audio'){
                        $validate['file_duration'] = 'required';
                    }
                    if($request->type != $question->type){
                        if($request->file){
                            if($request->type == 'pdf'){
                                $validate['file'] = "required|mimes:pdf";
                            }elseif($request->type == 'video'){
                                $validate['file'] = "required|mimes:mp4,avi,mov,wmv";
                            }elseif($request->type == 'audio'){
                                $validate['file'] = "required|mimes:mp3,wav,ogg";
                            }elseif($request->type == 'image'){
                                $validate['file'] = "required|mimes:jpeg,png,jpg,gif";
                            }elseif($request->type == 'slide document'){
                                $validate['file'] = "required|mimes:ppt,pptx";
                            }
                        }
                    }
                }
            }
        }

        if($request->hint){
            $validate['hint'] = 'required|string';
        }

        if($request->question_type != 'fill in blank'){
            $validate['answers.*.is_correct'] = 'required|in:1,0';
            $validate['answers.*.have_image'] = 'required|in:1,0';
        }

        if($request->different_point == 1){
            $validate['answers.*.point'] = 'required|integer';
        }else{
            $validate['point'] = 'required|integer';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkSubject = Subject::where([
            'uuid' => $request->subject_uuid,
        ])->first();

        if(!$checkSubject){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'subject_uuid' => [
                        "Subject not found",
                    ],
                ],
            ], 422);
        }

        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;

        if($request->type == 'text' || $request->type == 'youtube'){
            if($question->file_path){
                if (File::exists(public_path('storage/'.$question->file_path))) {
                    File::delete(public_path('storage/'.$question->file_path));
                }
            }
        }

        if($request->type != 'text'){
            if($request->type == 'youtube'){
                $url_path = $request->url_path;
            }else{
                $path = $question->file_path;
                $file_size = $question->file_size;
                if(!is_string($request->file)){
                    if($question->file_path){
                        if (File::exists(public_path('storage/'.$question->file_path))) {
                            File::delete(public_path('storage/'.$question->file_path));
                        }
                    }
                    if($request->file){
                        $file_size = $request->file->getSize();
                        $file_size = round($file_size / (1024 * 1024), 2);
                        $path = $request->file->store('questions', 'public');
                    }

                }
                $file_duration = $request->file_duration;
            }
        }

        $point = null;
        if($request->different_point == 0){
            $point = $request->point;
        }

        $validated=[
            'subject_uuid' => $request->subject_uuid,
            'question_type' => $request->question_type,
            'title' => $request->title,
            'question' => $request->question,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'type' => $request->type,
            'different_point' => $request->different_point,
            'point' => $point,
            'hint' => $request->hint,
            'file_path' => $path,
        ];


        $answersUuid = [];
        $newAnswers = [];

        foreach ($request->answers as $index => $answer) {
            $checkAnswer = Answer::where('uuid', $answer['uuid'])->first();

            if(!$checkAnswer){
                    $path = null;
                    if($answer['have_image'] == 1){
                        if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                            $path = $answer['image']->store('imagesAnswer', 'public');
                        }
                    }

                    $newAnswers[]=[
                        'uuid' => Uuid::uuid4()->toString(),
                        'question_uuid' => $question->uuid,
                        'answer' => $answer['answer'],
                        'image' => $path,
                        'is_correct' => $answer['is_correct'],
                        'point' => $answer['point'],
                    ];
            }else{
                $answersUuid[] = $checkAnswer->uuid;
                $path = $checkAnswer->image;
                if($answer['have_image'] == 1){
                    if(!is_string($answer['image'])){
                        $path = $answer['image']->store('imagesAnswer', 'public');
                        if($checkAnswer->image){
                            if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                                File::delete(public_path('storage/'.$checkAnswer->image));
                            }
                        }
                    }
                }else{
                    $path = null;
                    if($checkAnswer->image){
                        if (File::exists(public_path('storage/'.$checkAnswer->image))) {
                            File::delete(public_path('storage/'.$checkAnswer->image));
                        }
                    }
                }

                $point = null;
                $is_correct = null;

                if($request->question_type == 'most point'){
                    $is_correct = 1;
                    $point = $answer['point'];
                }else{
                    $is_correct = $answer['is_correct'];
                    if($request->different_point == 1){
                        $point = $answer['point'];
                    }
                }

                $validatedAnswer=[
                    'answer' => $answer['answer'],
                    'image' => $path,
                    'is_correct' => $is_correct,
                    'point' => $point,
                ];
                Answer::where('uuid', $checkAnswer->uuid)->update($validatedAnswer);
            }
        }
        Question::where('uuid', $uuid)->update($validated);
        Answer::where(['question_uuid' => $uuid])->whereNotIn('uuid', $answersUuid)->delete();
        if(count($newAnswers) > 0){
            Answer::insert($newAnswers);
        }

        return response()->json([
            'message' => 'Success update data',
        ], 200);
    }

    public function updateStatus(Request $request, $uuid){
        $question = Question::where('uuid', $uuid)->with(['answers'])->first();
        if(!$question){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $validate = [
            'status' => 'required|string|in:Published,Waiting for review,Draft',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkQuestionTest = QuestionTest::where([
            'question_uuid' => $question->uuid,
        ])->first();

        if($checkQuestionTest){
            return response()->json([
                'message' => 'This question has published and used in tests',
            ], 200);
        }

        Question::where('uuid', $uuid)->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Status changed',
        ], 200);
    }

    public function getBySubject($subject_uuid){
        try{
            $questions = Question::
                with('answers')
                ->where(['subject_uuid' => $subject_uuid])
                ->select('questions.uuid', 'questions.question_type', 'questions.question', 'questions.file_path', 'questions.url_path', 'questions.file_size', 'questions.file_duration', 'questions.type', 'subjects.name as subject_name')
                ->join('subjects', 'questions.subject_uuid', '=', 'subjects.uuid')
                ->get();

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

    public function show(Request $request, $detail){
        if($detail == 'multi choice' || $detail == 'most point' || $detail == 'single choice' || $detail == 'fill in blank' || $detail == 'true false'){
            try{

                $getQuestions = Question::
                select('questions.uuid', 'questions.question_type', 'questions.title', 'questions.question', 'questions.file_path', 'questions.url_path', 'questions.file_size', 'questions.file_duration', 'questions.type', 'questions.different_point', 'questions.point', 'questions.hint', 'questions.status', 'subjects.name as subject_name', 'users.name as author_name')
                ->join('subjects', 'questions.subject_uuid', '=', 'subjects.uuid')
                ->join('users', 'users.author_uuid', '=', 'users.uuid')
                ->where(['questions.question_type' => $detail])
                ->with(['answers'])
                ->get();

                $questions = [];
                foreach ($getQuestions as $index => $question) {
                    $questions[] = [
                        'uuid' => $question->uuid,
                        'subject_name' => $question->subject_name,
                        'author_name' => $question->author_name,
                        'title' => $question->title,
                        'question' => $question->question,
                        'question_type' => $question->question_type,
                        'file_path' => $question->file_path,
                        'url_path' => $question->url_path,
                        'file_size' => $question->file_size,
                        'file_duration' => $question->file_duration,
                        'type' => $question->type,
                        'status' => $question->status,
                        'different_point' => $question->different_point,
                        'point' => $question->point,
                        'hint' => $question->hint,
                        'answers' => $question->answers,
                    ];
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
        }else{
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
