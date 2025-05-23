<?php

namespace App\Http\Controllers\Pauli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

use App\Models\PauliRecord;

class RecordDataController extends Controller
{
    public function getPauli($pauli_id){
        $pauli = PauliRecord::where('id', $pauli_id)->first();
        if($pauli){
            return response()->json([
                'message' => 'Pauli record found',
                'pauli' => $pauli
            ], 200);
        }else{
            return response()->json([
                'message' => 'Pauli record not found',
            ], 404);
        }
    }
    public function postRecord(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $userUuid = $user->uuid;

        $validator = Validator::make($request->all(), [
            // "user_uuid" => "required|string",
            'selected_time' => 'required|in:1,2,5,10,15,30,60',
            'questions_attempted' => 'required|integer|min:0',
            'total_correct' => 'required|integer|min:0',
            'date' => 'required|date_format:Y-m-d',
            'correct_datas' => 'required|array',
            'incorrect_datas' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pauliRecord = PauliRecord::create([
            // 'user_uuid' => $request->user_uuid,
            'user_uuid' => $userUuid,
            'selected_time' => $request->selected_time,
            'questions_attempted' => $request->questions_attempted,
            'total_correct' => $request->total_correct,
            'total_wrong' => $request->questions_attempted - $request->total_correct,
            'time_start' => $request->time_start,
            'time_end' => $request->time_end,
            'date' => $request->date,
            'correct_datas' => $request->correct_datas,
            'incorrect_datas' => $request->incorrect_datas,
        ]);

        return response()->json([
            'message' => 'Overall result has been saved successfully.',
            'pauli_record' => $pauliRecord,
        ], 200);
    }
}
