<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        # except for authenticate/login & register
        $this->middleware(['auth:api', 'admin']);
    }

    public function index(){

        try{
            $users = User::select('uuid', 'role', 'name', 'username', 'email', 'mobile_number', 'gender', 'avatar')->get();
            return response()->json([
                'message' => 'Success get data',
                'users' => $users,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

    }

    public function store(Request $request): JsonResponse{
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'role' => 'required|in:admin,instructor,student',
            'email' => 'required|email|unique:users',
            'username' => 'required|unique:users',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user =  User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role' => $request->role,
        ]);
        $user->sendEmailVerificationNotification();
        return response()->json([
            'message' => 'Success create new user'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $user = User::where(['uuid' => $uuid])->first();

        if(!$user){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $validate = [
            'name' => 'required',
            'role' => 'required|in:admin,instructor,student',
        ];

        if($user->email != $request->email){
            $validate['email'] = 'required|email|unique:users';
        }

        if($user->username != $request->username){
            $validate['username'] = 'required|unique:users';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = [
            'name' => $request->name,
            'role' => $request->role,
        ];

        if($user->email != $request->email){
            $validated['email'] = $request->email;
        }

        if($user->username != $request->username){
            $validated['username'] = $request->username;
        }

        $user =  User::where(['uuid' => $uuid])->update($validated);
        return response()->json([
            'message' => 'Success update user'
        ], 200);
    }

    public function delete(Request $request, $uuid): JsonResponse{
        $user = User::where(['uuid' => $uuid])->first();

        if(!$user){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user =  User::where(['uuid' => $uuid])->delete();
        return response()->json([
            'message' => 'Success delete user'
        ], 200);
    }
}
