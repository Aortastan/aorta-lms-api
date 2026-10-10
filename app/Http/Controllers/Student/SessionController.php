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

        $redisKey = "test_session:{$session_uuid}";
        $now = time();

        // 1. Simpan selalu ke Redis (in-memory, sangat cepat, 0 disk I/O)
        try {
            $sessionData = [
                'duration_left' => $request->duration_left,
                'data_question' => $request->data_question,
                'updated_at'    => $now,
            ];
            \Illuminate\Support\Facades\Redis::setex($redisKey, 86400, json_encode($sessionData));
        } catch (\Throwable $e) {
            // Jika Redis offline, failover aman lanjut ke DB
        }

        // 2. Throttle penulisan ke MySQL: Hanya tulis ke DB maksimal 1x per 15 detik per session
        // atau jika belum pernah ada timestamp last_db_write di Redis
        $lastWriteKey = "test_session_last_db:{$session_uuid}";
        $shouldWriteDb = true;

        try {
            $lastWrite = \Illuminate\Support\Facades\Redis::get($lastWriteKey);
            if ($lastWrite && ($now - (int)$lastWrite) < 15) {
                $shouldWriteDb = false;
            }
        } catch (\Throwable $e) {
            $shouldWriteDb = true;
        }

        if ($shouldWriteDb) {
            $affected = SessionTest::where(['uuid' => $session_uuid])->update([
                'duration_left' => $request->duration_left,
                'data_question' => json_encode($request->data_question),
            ]);

            if ($affected === 0) {
                $exists = SessionTest::where(['uuid' => $session_uuid])->exists();
                if (!$exists) {
                    return response()->json([
                        'message' => 'Session tidak ditemukan'
                    ], 404);
                }
            }

            try {
                \Illuminate\Support\Facades\Redis::setex($lastWriteKey, 60, (string)$now);
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'message' => 'Session berhasil diupdate'
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
