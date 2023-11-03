<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

class QuestionController extends Controller
{
    public function index(){
        try{
            $questions = Question::select('uuid', 'question_type', 'question', 'file_path', 'url_path', 'file_size', 'file_duration', 'file_duration_seconds', 'type')->with(['answers'])->get();
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
        if($detail == 'multiple' || $detail == 'most point'){
            try{
                $questions = Question::select('uuid', 'question_type', 'question', 'file_path', 'url_path', 'file_size', 'file_duration', 'file_duration_seconds', 'type')->where(['question_type' => $detail])->with(['answers'])->get();
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
                $question = Question::select('uuid', 'question_type', 'question', 'file_path', 'url_path', 'file_size', 'file_duration', 'file_duration_seconds', 'type')->where(['uuid' => $detail])->with(['answers'])->first();
                if($question == null){
                    return response()->json([
                        'message' => 'Data not found',
                    ], 404);
                }
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

    public function store(Request $request){
        $validate = [
            'question' => 'required|string',
            'question_type' => 'required|in:multiple,most point',
            'type' => 'required|in:video,youtube,text,image,pdf,audio,slide document',
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.answer' => 'required|string',
        ];

        if($request->type){
            if($request->type != 'text'){
                if($request->type == 'youtube'){
                    $validate['url_path'] = 'required';
                    $validate['file_duration'] = 'required';
                    $validate['file_duration_seconds'] = 'required';
                }else{
                    if($request->type == 'video' || $request->type == 'audio'){
                        $validate['file_duration'] = 'required';
                        $validate['file_duration_seconds'] = 'required';
                    }
                    $validate['file'] = "required";
                    $validate['file_size'] = "required";
                }
            }
        }else{
            return response()->json([
                'message' => 'Validation failed',
            ], 422);
        }

        if($request->question_type){
            if($request->question_type == 'multiple'){
                $validate['answers.*.is_correct'] = 'required|boolean';
            }else{
                $validate['answers.*.point'] = 'required|numeric';
            }
        }else{
            return response()->json([
                'message' => 'Validation failed',
            ], 422);
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;
        $file_duration_seconds = null;
        if($request->type != 'text'){
            if($request->type == 'youtube'){
                $url_path = $request->url_path;
            }else{
                $path = $request->file->store('questions', 'public');
                $file_size = $request->file_size;
                $file_duration = $request->file_duration;
                $file_duration_seconds = $request->file_duration_seconds;
            }
        }

        $validated=[
            'question_type' => $request->question_type,
            'question' => $request->question,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'file_duration_seconds' => $file_duration_seconds,
            'type' => $request->type,
            'file_path' => $path,
        ];

        $question = Question::create($validated);
        $answers = [];
        foreach ($request->answers as $index => $answer) {
            $path = null;
            if($answer['image']){
                if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                    $path = $answer['image']->store('imagesAnswer', 'public');
                }
            }

            $is_correct = null;
            $point = null;
            if($request->question_type == 'multiple'){
                $is_correct = $answer['is_correct'];
            }else{
                $point = $answer['point'];
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

        $validate = [
            'question_type' => 'required|in:multiple,most point',
            'type' => 'required|in:video,youtube,text,image,pdf,slide document',
            'answers' => 'required|array',
            'answers.*' => 'array',
            'answers.*.answer' => 'required|string',
        ];

        if($request->type != null){
            if($request->type != 'text'){
                if($request->type == 'youtube'){
                    $validate['url_path'] = 'required';
                }else{
                    if($request->type == 'video'){
                        $validate['file_duration'] = 'required';
                        $validate['file_duration_seconds'] = 'required';
                    }
                    $validate['file'] = "required";
                    $validate['file_size'] = "required";
                }
            }
        }else{
            return response()->json([
                'message' => 'Validation failed',
            ], 422);
        }

        if($request->question_type){
            if($request->question_type == 'multiple'){
                $validate['answers.*.is_correct'] = 'required|boolean';
            }else{
                $validate['answers.*.point'] = 'required|numeric';
            }
        }else{
            return response()->json([
                'message' => 'Validation failed',
            ], 422);
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;
        $file_duration_seconds = null;

        // jika berupa text dan youtube (bukan file)=> hapus file
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
                if(!is_string($request->file)){
                    if($question->file_path){
                        if (File::exists(public_path('storage/'.$question->file_path))) {
                            File::delete(public_path('storage/'.$question->file_path));
                        }
                    }
                    $path = $request->file->store('questions', 'public');
                }
                $file_size = $request->file_size;
                $file_duration = $request->file_duration;
                $file_duration_seconds = $request->file_duration_seconds;
            }
        }

        $validated=[
            'question_type' => $request->question_type,
            'question' => $request->question,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'file_duration_seconds' => $file_duration_seconds,
            'type' => $request->type,
            'file_path' => $path,
        ];


        $answersUuid = [];
        $newAnswers = [];

        foreach ($request->answers as $index => $answer) {
            $checkAnswer = Answer::where('uuid', $answer['uuid'])->first();

            if(!$checkAnswer){
                    $path = null;
                    if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                        $path = $answer['image']->store('imagesAnswer', 'public');
                    }

                    $is_correct = null;
                    $point = null;
                    if($request->question_type == 'multiple'){
                        $is_correct = $answer['is_correct'];
                    }else{
                        $point = $answer['point'];
                    }
                    $newAnswers[]=[
                        'uuid' => Uuid::uuid4()->toString(),
                        'question_uuid' => $question->uuid,
                        'answer' => $answer['answer'],
                        'image' => $path,
                        'is_correct' => $is_correct,
                        'point' => $point,
                    ];
            }else{
                $answersUuid[] = $checkAnswer->uuid;
                $path = $checkAnswer->image;
                if($answer['image'] instanceof \Illuminate\Http\UploadedFile && $answer['image']->isValid()){
                    $path = $answer['image']->store('imagesAnswer', 'public');
                    if(!is_string($answer['image'])){
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

                $is_correct = null;
                $point = null;
                if($request->question_type == 'multiple'){
                    $is_correct = $answer['is_correct'];
                }else{
                    $point = $answer['point'];
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

    public function delete(Request $request, $uuid){
        Question::where(['uuid' => $uuid])->delete();
        Answer::where(['question_uuid' => $uuid])->delete();
        return response()->json([
            'message' => "Success delete data",
        ], 200);
    }
}
