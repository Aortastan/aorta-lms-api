<?php

namespace App\Http\Controllers\Pauli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Package;
use App\Models\Test;
use App\Models\MembershipHistory;
use App\Models\PackageTest;
use App\Models\PauliRecord;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Namshi\JOSE\JWT;

class UserController extends Controller
{
    public function checkEligibility(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $userUuid = $user->uuid;

        // Cek apakah user dengan user_uuid ada
        $user = User::find($userUuid);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Cek package yang dimiliki user di tabel pivot membership_history
        $currentDate = Carbon::now();
        $validPackages = MembershipHistory::where('user_uuid', $userUuid)
            ->where('expired_date', '>', $currentDate)
            ->pluck('package_uuid');

        if ($validPackages->isEmpty()) {
            return response()->json([
                'status' => 'NOT OK',
                'message' => "Student {$user->name} tidak memiliki package apapun"
            ], 200);
        }

        // Cari test yang valid dari pivot table antara package dan test dengan package_uuid yang didapat dari $validPackages
        $validTestUuids = PackageTest::whereIn('package_uuid', $validPackages)
            ->pluck('test_uuid');

        // Lalu ketika sudah dapat tiap test_uuid yang valid, baru dicari title yang sesuai di tabel test
        $hasValidTest = Test::whereIn('uuid', $validTestUuids)
            ->where(function ($query) {
                $query->where('title', 'LIKE', '%pauli%')
                    ->orWhere('title', 'LIKE', '%Pauli%')
                    ->orWhere('title', 'LIKE', '%koran%')
                    ->orWhere('title', 'LIKE', '%Koran%');
            })
            ->exists();

        if ($hasValidTest) {
            return response()->json(['message' => "Student {$user->name} valid mengakses Tes Pauli Durasi 60 Menit", 'status' => 'OK'], 200);
        } else {
            return response()->json([
                'status' => 'NOT OK',
                'message' => "Student {$user->name} tidak memiliki package yang mengandung paket tes pauli"
            ], 200);
        }
    }

    // Pauli Test History
    public function userHistory()
    {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userUuid = $user->uuid;

        $pauliRecords = PauliRecord::where('user_uuid', $userUuid)
        ->orderBy('date', 'desc')
        ->get()
        ->makeHidden(['correct_datas', 'incorrect_datas', 'created_at', 'updated_at', 'time_end', 'time_start']);

        return response()->json(['data' => $pauliRecords], 200);
    }
}
