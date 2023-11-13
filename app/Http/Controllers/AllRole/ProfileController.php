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


class ProfileController extends Controller
{
    public
    $user;

    public function __construct(){
        $this->user = JWTAuth::parseToken()->authenticate();
    }

    public function index(){
        try{
            $profile = User::
            select('name', 'role', 'username', 'email', 'mobile_number', 'gender', 'avatar')
            ->where([
                'uuid' => $this->user->uuid,
            ])->first();

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
        $validate = [
            'name' => 'required|string',
            'username' => 'required|string',
            'mobile_number' => "required|string",
            'gender' => "required|string|in:male,female",
        ];

        if($this->user->username != $request->username){
            $validate['username'] = 'required|string|unique:users';
        }

        if($this->user->mobile_number != $request->mobile_number){
            $validate['mobile_number'] = 'required|string|unique:users';
        }

        if($request->avatar instanceof \Illuminate\Http\UploadedFile && $request->avatar->isValid()){
            $validate['avatar'] = 'required|image';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = null;

        if($request->avatar == null){
            if (File::exists(public_path('storage/'.$this->user->avatar))) {
                File::delete(public_path('storage/'.$this->user->avatar));
            }
        }

        if($request->avatar instanceof \Illuminate\Http\UploadedFile && $request->avatar->isValid()){
            $path = $request->avatar->store('imagesProfile', 'public');
            if (File::exists(public_path('storage/'.$this->user->avatar))) {
                File::delete(public_path('storage/'.$this->user->avatar));
            }
        }elseif(is_string($request->avatar)){
            $path = $this->user->avatar;
        }

        User::where([
            'uuid' => $this->user->uuid,
        ])->update([
            "name" => $request->name,
            "username" => $request->username,
            "mobile_number" => $request->mobile_number,
            "gender" => $request->gender,
            "avatar" => $path,
        ]);

        return response()->json([
            'message' => 'Update profile successfully',
        ], 200);
    }

    public function changePassword(Request $request): JsonResponse{
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
                'uuid' => $this->user->uuid,
            ])->first();

        if (!Hash::check($request->current_password, $profile->password)) {
            return response()->json([
                'message' => 'Current password not valid',
            ], 422);
        }

        User::
            where([
                'uuid' => $this->user->uuid,
            ])->update([
                "password" => Hash::make($request->new_password)
            ]);

        return response()->json([
            'message' => 'Successfully change password',
        ], 200);
    }
}
