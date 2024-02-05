<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TemplateAnswer;
use App\Models\TemplateQuestion;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;
use File;
use Illuminate\Support\Str;

class TemplateQuestionController extends Controller
{
    public function index(Request $request){
        try{

            $search = "";

            $questions = DB::table('template_questions')->select('template_questions.uuid', 'template_questions.title', 'template_questions.question_type', 'template_questions.question', 'template_questions.file_path', 'template_questions.url_path', 'template_questions.file_size', 'template_questions.file_duration', 'template_questions.type', 'template_questions.status', 'subjects.name as subject_name', 'users.name as author_name', 'users.avatar as author_image')
                ->join('users', 'template_questions.author_uuid', '=', 'users.uuid')
                ->join('subjects', 'template_questions.subject_uuid', '=', 'subjects.uuid');

            if(isset($_GET['search'])){
                $questions->where('template_questions.title', 'LIKE', '%'.$_GET['search'].'%');
            }

            if(isset($_GET['question_type'])){
                $questions->where('template_questions.question_type', $_GET['question_type']);
            }

            if(isset($_GET['type'])){
                $questions->where('template_questions.type', $_GET['type']);
            }

            if(isset($_GET['status'])){
                $questions->where('template_questions.status', $_GET['status']);
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
        $question = Question::where('uuid', $request->question_uuid)->with(['answers'])->first();

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

                    // if dibawah digunakan untuk menghandle, jika user save template di saat soal belum tersimpan di questions table, maka dihandle layaknya membuat soal baru
                    // jika sudah ada question sebelumnya, maka cukup mengecek apakah file upload ulang atau menggunakan data dari table question

                    if($question){
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
                    }else{
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

        if($request->type != 'text'){
            if($request->type == 'youtube'){
                $url_path = $request->url_path;
            }else{
                if($request->file){
                    $file_size = $request->file->getSize();
                    $path = $request->file->store('questions', 'public');
                    $file_size = round($file_size / (1024 * 1024), 2); //Megabytes
                    $file_duration = $request->file_duration;
                }else{
                    if($question){
                        if (File::exists(public_path('storage/'.$question->file_path))) {
                            $sourcePath = public_path('storage/'.$question->file_path);
                            $destinationPath = public_path('storage/templates/'.$question->file_path);
                            // Salin file
                            File::copy($sourcePath, $destinationPath);
                            $file_size = $question->file_size;
                            $path = 'templates/' . $question->file_path;
                            $file_duration = $question->file_duration;
                        }
                    }
                }
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

        $templateQuestion = TemplateQuestion::create($validated);

        $answers = [];
        foreach ($request->answers as $index => $answer) {
            $path = null;
            if($answer['have_image'] == 1){
                // jika upload gambar baru
                if($answer['image']){
                    $mime = $answer['image']->getMimeType();
                    // Pengecekan apakah tipe MIME adalah tipe gambar
                    if (Str::startsWith($mime, 'image/')) {
                        // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                        $path = $answer['image']->store('imagesAnswer', 'public');
                    }
                }else{ // jika gambarnya sudah ada di answer sebelumnya
                    if (File::exists(public_path('storage/'.$question->answers[$index]['image']))) {
                        $sourcePath = public_path('storage/'.$question->answers[$index]['image']);
                        $destinationPath = public_path('storage/templates/'.$question->answers[$index]['image']);
                        // Salin file
                        File::copy($sourcePath, $destinationPath);
                        $path = 'templates/' . $question->answers[$index]['image'];
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
                'question_uuid' => $templateQuestion->uuid,
                'answer' => $answer['answer'],
                'image' => $path,
                'is_correct' => $is_correct,
                'point' => $point,
            ];
        }

        TemplateAnswer::insert($answers);

        return response()->json([
            'message' => 'Success post data',
        ], 200);
    }

    public function update(Request $request, $template_uuid){
        $templateQuestion = TemplateQuestion::where('uuid', $template_uuid)->with(['answers'])->first();
        if(!$templateQuestion){
            return response()->json([
                'message' => 'Data template not found',
            ], 404);
        }

        $question = Question::where('uuid', $request->question_uuid)->with(['answers'])->first();

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

                    // if dibawah digunakan untuk menghandle, jika user save template di saat soal belum tersimpan di questions table, maka dihandle layaknya membuat soal baru
                    // jika sudah ada question sebelumnya, maka cukup mengecek apakah file upload ulang atau menggunakan data dari table question

                    if($question){
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
                    }else{
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


        // lakukan proses delete semua data terlebih dahulu
        $getAllAnswers = TemplateAnswer::where([
            'question_uuid' => $template_uuid,
        ])->get();

        foreach ($getAllAnswers as $index => $answer) {
            if($answer['image']){
                if (File::exists(public_path('storage/'.$answer->image))) {
                    File::delete(public_path('storage/'.$answer->image));
                }
            }
        }

        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;

        if($request->type != 'text'){
            if($request->type == 'youtube'){
                $url_path = $request->url_path;
            }else{
                if($request->file){
                    $file_size = $request->file->getSize();
                    $path = $request->file->store('questions', 'public');
                    $file_size = round($file_size / (1024 * 1024), 2); //Megabytes
                    $file_duration = $request->file_duration;
                }else{
                    if($question){
                        if (File::exists(public_path('storage/'.$question->file_path))) {
                            $sourcePath = public_path('storage/'.$question->file_path);
                            $destinationPath = public_path('storage/templates/'.$question->file_path);
                            // Salin file
                            File::copy($sourcePath, $destinationPath);
                            $file_size = $question->file_size;
                            $path = 'templates/' . $question->file_path;
                            $file_duration = $question->file_duration;
                        }
                    }
                }
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

        $templateQuestion = TemplateQuestion::where(['uuid' => $template_uuid])->update($validated);

        $answers = [];
        foreach ($request->answers as $index => $answer) {
            $path = null;
            if($answer['have_image'] == 1){
                // jika upload gambar baru
                if($answer['image']){
                    $mime = $answer['image']->getMimeType();
                    // Pengecekan apakah tipe MIME adalah tipe gambar
                    if (Str::startsWith($mime, 'image/')) {
                        // Tipe MIME sesuai dengan gambar, lanjutkan penyimpanan
                        $path = $answer['image']->store('imagesAnswer', 'public');
                    }
                }else{ // jika gambarnya sudah ada di answer sebelumnya
                    if (File::exists(public_path('storage/'.$question->answers[$index]['image']))) {
                        $sourcePath = public_path('storage/'.$question->answers[$index]['image']);
                        $destinationPath = public_path('storage/templates/'.$question->answers[$index]['image']);
                        // Salin file
                        File::copy($sourcePath, $destinationPath);
                        $path = 'templates/' . $question->answers[$index]['image'];
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
                'question_uuid' => $template_uuid,
                'answer' => $answer['answer'],
                'image' => $path,
                'is_correct' => $is_correct,
                'point' => $point,
            ];
        }

        TemplateAnswer::insert($answers);

        return response()->json([
            'message' => 'Success post data',
        ], 200);
    }

    public function show(Request $request, $uuid){
        try{
            $getQuestion = TemplateQuestion::where(['uuid' => $uuid])->with(['answers'])->first();

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
