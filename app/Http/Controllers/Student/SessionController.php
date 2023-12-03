<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SessionTest;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    public function update(Request $request, $session_uuid){
        $validate = [
            'duration_left' => 'required',
            'data_question' => 'required',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user_session = SessionTest::where(['uuid' => $session_uuid])->first();
        if($user_session == null){
            return response()->json([
                'message'=>'Session not found'
            ], 404);
        }

        SessionTest::where(['uuid' => $session_uuid])->update([
            'duration_left' => $request->duration_left,
            'data_question' => json_encode($request->data_question),
        ]);

        return response()->json([
            'message'=>'Session updated'
        ], 200);
    }
}
