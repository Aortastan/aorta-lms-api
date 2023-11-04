<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\PretestPosttest;
use App\Models\CourseLesson;
use App\Models\LessonLecture;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use File;

class LessonLectureController extends Controller
{
    public function show($uuid){
        try{
            $lecture = LessonLecture::select('uuid', 'lesson_uuid', 'title', 'body', 'file_path', 'url_path', 'file_size', 'file_duration', 'file_duration_seconds', 'type')->where(['uuid' => $uuid])->first();

            return response()->json([
                'message' => 'Success get data',
                'lecture' => $lecture,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request){
        $checkLesson = CourseLesson::where(['uuid' => $request->lesson_uuid])->first();
        if(!$checkLesson){
            return response()->json([
                'message' => 'Lesson not found',
            ], 404);
        }
        $validate = [
            'title' => 'required',
            'body' => 'required',
            'type' => 'required|in:video,youtube,text,image,pdf,slide document,audio',
        ];

        if($request->type == "youtube"){
            $validate['url_path'] = "required";
        }

        if($request->type != "youtube" && $request->type != "text"){
            $validate['file'] = "required";
        }

        if($request->type == "youtube" || $request->type == "video" || $request->type == "audio"){
            $validate['file_duration'] = "required";
            $validate['file_duration_seconds'] = "required";
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file_path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;
        $file_duration_seconds = null;

        if($request->type != "youtube" && $request->type != "text"){
            $file_size = $request->file->getSize();
            $file_path = $request->file->store('lectures', 'public');
            $file_size = round($file_size / (1024 * 1024), 2);
        }
        if($request->type == "youtube"){
            $url_path = $request->url_path;
        }

        if($request->type == "youtube" || $request->type == "video" || $request->type == "audio"){
            $file_duration = $request->file_duration;
            $file_duration_seconds = $request->file_duration_seconds;
        }

        $validated = [
            'lesson_uuid' => $request->lesson_uuid,
            'title' => $request->title,
            'body' => $request->body,
            'type' => $request->type,
            'file_path' => $file_path,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'file_duration_seconds' => $file_duration_seconds,
        ];

        $lecture = LessonLecture::create($validated);

        return response()->json([
            'message' => 'Success create new lecture'
        ], 200);

    }

    public function update(Request $request, $uuid){
        $checkLecture = LessonLecture::where(['uuid' => $uuid])->first();
        if(!$checkLecture){
            return response()->json([
                'message' => 'Lecture not found',
            ], 404);
        }

        $validate = [
            'title' => 'required|string',
            'body' => 'required|string',
            'type' => 'required|in:video,youtube,text,image,pdf,slide document,audio',
        ];

        if($request->type != "youtube" && $request->type != "text"){
            $validate['file'] = "required";
        }
        if($request->type == "youtube"){
            $validate['url_path'] = "required";
        }

        if($request->type == "youtube" || $request->type == "video" || $request->type == "audio"){
            $validate['file_duration'] = "required";
            $validate['file_duration_seconds'] = "required";
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file_path = null;
        $url_path = null;
        $file_size = null;
        $file_duration = null;
        $file_duration_seconds = null;

        if($request->type == "text" || $request->type == "youtube"){
            if (File::exists(public_path('storage/'.$checkLecture->file_path))) {
                File::delete(public_path('storage/'.$checkLecture->file_path));
            }
        }

        if($request->type != "youtube" && $request->type != "text"){
            if(!is_string($request->file)){
                $file_size = $request->file->getSize();
                $file_path = $request->file->store('lectures', 'public');
                $file_size = round($file_size / (1024 * 1024), 2);
                if (File::exists(public_path('storage/'.$checkLecture->file_path))) {
                    File::delete(public_path('storage/'.$checkLecture->file_path));
                }
            }else{
                $file_path = $checkLecture->file_path;
                $file_size = $checkLecture->file_size;
            }
        }
        if($request->type == "youtube"){
            $url_path = $request->url_path;
        }

        if($request->type == "youtube" || $request->type == "video" || $request->type == "audio"){
            $file_duration = $request->file_duration;
            $file_duration_seconds = $request->file_duration_seconds;
        }

        $validated = [
            'title' => $request->title,
            'body' => $request->body,
            'type' => $request->type,
            'file_path' => $file_path,
            'url_path' => $url_path,
            'file_size' => $file_size,
            'file_duration' => $file_duration,
            'file_duration_seconds' => $file_duration_seconds,
        ];

        $lecture = LessonLecture::where(['uuid' => $uuid])->update($validated);

        return response()->json([
            'message' => 'Success update lecture'
        ], 200);

    }
}
