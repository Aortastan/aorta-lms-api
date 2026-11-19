<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;


class ProfileController extends Controller
{
    private function getUser()
    {
        return auth()->user() ?? JWTAuth::parseToken()->authenticate();
    }

    public function index(){
        try{
            $user = $this->getUser();
            $profile = \Illuminate\Support\Facades\Cache::remember("user_profile:{$user->uuid}", 300, function () use ($user) {
                return User::select('uuid', 'name', 'role', 'username', 'email', 'mobile_number', 'gender', 'avatar', 'name_verified_at', 'name_verified_by')
                    ->where([
                        'uuid' => $user->uuid,
                    ])->first();
            });

            return response()->json([
                'message' => 'Success get data',
                'profile' => $profile,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function update(Request $request){
        $user = $this->getUser();

        $cleanedNumber = $this->normalizeMobileNumber($request->mobile_number);

        $validate = [
            'name' => 'required|string',
            'username' => 'required|string',
            'mobile_number' => [
                'required',
                'string',
                'regex:/^\+62[0-9]{8,13}$/',
                Rule::unique('users', 'mobile_number')->ignore($user->uuid, 'uuid'),
            ],
            'gender' => "string|in:male,female",
        ];

        if($user->username != $request->username){
            $validate['username'] = 'required|string|unique:users';
        }

        if($request->avatar instanceof \Illuminate\Http\UploadedFile && $request->avatar->isValid()){
            $validate['avatar'] = 'required|image';
        }

        $input = $request->all();
        if (trim((string) $request->mobile_number) !== '') {
            $input['mobile_number'] = $cleanedNumber;
        }

        $validator = Validator::make($input, $validate, [
            'mobile_number.required' => 'Nomor telepon wajib diisi',
            'mobile_number.regex' => 'Nomor telepon tidak valid. Contoh: 081234567890 atau +6281234567890',
            'mobile_number.unique' => 'Nomor telepon sudah digunakan akun lain',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Nama yang sudah diverifikasi admin tidak boleh diubah lagi oleh user sendiri
        if ($user->name_verified_at && $user->name !== $request->name) {
            return response()->json([
                'message' => 'Nama Anda sudah diverifikasi admin dan tidak dapat diubah. Hubungi admin jika ada perubahan.',
                'errors' => [
                    'name' => ['Nama sudah terkunci karena terverifikasi'],
                ],
            ], 422);
        }

        $path = null;

        if($request->avatar == null){
            if (File::exists(public_path('storage/'.$user->avatar))) {
                File::delete(public_path('storage/'.$user->avatar));
            }
        }

        if($request->avatar instanceof \Illuminate\Http\UploadedFile && $request->avatar->isValid()){
            $path = $request->avatar->store('imagesProfile', 'public');
            if (File::exists(public_path('storage/'.$user->avatar))) {
                File::delete(public_path('storage/'.$user->avatar));
            }
        }elseif(is_string($request->avatar)){
            $path = $user->avatar;
        }

        try {
            User::where([
                'uuid' => $user->uuid,
            ])->update([
                "name" => $request->name,
                "username" => $request->username,
                "mobile_number" => $cleanedNumber,
                "gender" => $request->gender,
                "avatar" => $path,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) == 1062) {
                $field = strpos($e->getMessage(), 'mobile_number') !== false ? 'mobile_number' : 'username';
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        $field => [$field == 'mobile_number' ? 'Nomor telepon sudah digunakan akun lain' : 'Username sudah digunakan akun lain'],
                    ],
                ], 422);
            }
            throw $e;
        }

        \Illuminate\Support\Facades\Cache::forget("user_profile:{$user->uuid}");

        return response()->json([
            'message' => 'Update profile successfully',
            'profile' => [
                'mobile_number' => $cleanedNumber,
            ],
        ], 200);
    }

    private function normalizeMobileNumber($mobileNumber)
    {
        $cleanedNumber = ltrim(preg_replace('/[^0-9]/', '', (string) $mobileNumber), '0');

        return substr($cleanedNumber, 0, 2) !== '62' ? '+62' . $cleanedNumber : '+' . $cleanedNumber;
    }

    public function changePassword(Request $request): JsonResponse{
        $user = $this->getUser();

        $validate = [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|same:confirm_new_password',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = User::
            select('password')
            ->where([
                'uuid' => $user->uuid,
            ])->first();

        if (!Hash::check($request->current_password, $profile->password)) {
            return response()->json([
                'message' => 'Current password not valid',
            ], 422);
        }

        User::
            where([
                'uuid' => $user->uuid,
            ])->update([
                "password" => Hash::make($request->new_password)
            ]);

        return response()->json([
            'message' => 'Successfully change password',
        ], 200);
    }
}
