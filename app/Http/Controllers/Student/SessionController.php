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

        foreach ($request->data_question as $index => $question) {
            if($question['status'] == 2){
                if(($question["answer_uuid"]) <= 0){
                    return response()->json([
                        'message'=>'Session berhasil diupdate'
                    ], 200);
                }
            }
        }

        $user_session = SessionTest::where(['uuid' => $session_uuid])->first();
        if($user_session == null){
            return response()->json([
                'message'=>'Session tidak ditemukan'
            ], 404);
        }

        SessionTest::where(['uuid' => $session_uuid])->update([
            'duration_left' => $request->duration_left,
            'data_question' => json_encode($request->data_question),
        ]);

        return response()->json([
            'message'=>'Session berhasil diupdate'
        ], 200);

    //         $validator = Validator::make($request->all(), [
    //     'duration_left' => 'required|numeric|min:0',
    //     'data_question' => 'required|array',
    // ]);

    // if ($validator->fails()) {
    //     return response()->json([
    //         'status' => false,
    //         'message' => 'Validation failed',
    //         'errors' => $validator->errors(),
    //     ], 422);
    // }

    // $user = auth()->user();
    // $key = "tryout_session:{$user->uuid}:{$package_test_uuid}";

    // $session = Redis::get($key);

    // if (!$session) {
    //     return response()->json([
    //         'status' => false,
    //         'message' => 'Session tidak ditemukan'
    //     ], 404);
    // }

    // $session = is_string($session) ? json_decode($session, true) : $session;

    // /**
    //  * VALIDASI: status 2 wajib ada jawaban
    //  */
    // foreach ($request->data_question as $q) {
    //     if (
    //         isset($q['status']) &&
    //         $q['status'] == 2 &&
    //         (empty($q['answer_uuid']) || count($q['answer_uuid']) <= 0)
    //     ) {
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Session berhasil diupdate'
    //         ], 200);
    //     }
    // }

    // // UPDATE DATA
    // $session['duration_left'] = (int) $request->duration_left;
    // $session['data_question'] = $request->data_question;
    // $session['updated_at'] = now()->toDateTimeString();
    // $ttl = (int) $request->duration_left * 60; // MENIT → DETIK

    // Redis::set($key, json_encode($session));
    // Redis::expire($key, $ttl);

    // return response()->json([
    //     'status' => true,
    //     'message' => 'Session berhasil diupdate'
    // ], 200);
    }
}
