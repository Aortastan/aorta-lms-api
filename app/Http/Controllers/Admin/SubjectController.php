<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SubjectController extends Controller
{
    public function index(){
        try{
            $subjects = Subject::select('uuid', 'name')->get();
            return response()->json([
                'message' => 'Success get data',
                'subjects' => $subjects,
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
            $subject = Subject::select('uuid', 'name')->where(['uuid' => $uuid])->first();

            if(!$subject){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
            }
            return response()->json([
                'message' => 'Success get data',
                'subject' => $subject,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse{
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:subjects',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        Subject::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Success create new subject'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkSubject = Subject::where(['uuid' => $uuid])->first();
        if(!$checkSubject){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        if($checkSubject->name != $request->name){
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:subjects',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Subject::where(['uuid' => $uuid])->update([
                'name' => $request->name,
            ]);
        }


        return response()->json([
            'message' => 'Success update subject'
        ], 200);
    }

    public function delete(Request $request, $uuid){
        $checkSubject = Subject::where(['uuid' => $uuid])->first();
        if(!$checkSubject){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $checkSubjectQuestion = Question::where([
            'subject_uuid' => $checkSubject->uuid
        ])->first();

        if($checkSubjectQuestion){
            return response()->json([
                'message' => 'You can\'t delete it, the subject already used in question'
            ], 422);
        }

        Subject::where(['uuid' => $uuid])->delete();


        return response()->json([
            'message' => 'Success delete subject'
        ], 200);
    }
}
