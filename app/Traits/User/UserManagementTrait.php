<?php
namespace App\Traits\User;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

trait UserManagementTrait
{
    public function usersByRole($role)
    {
        try{

            return response()->json([
                'message' => 'Success get data',
                'users' => User::select('uuid', 'role', 'name', 'username', 'email', 'mobile_number', 'gender', 'avatar')->where(['role' => $role])->get(),
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function storeValidation($request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
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

        return null;
    }

    public function getUser($uuid, $role){
        $user = User::where(['uuid' => $uuid, 'role' => $role])->first();
        return $user;
    }

    public function updateValidation($request, $user){
        $validate = [
            'name' => 'required',
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

        return null;
    }


    public function storeUser($request, $role){
        $user =  User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role' => $role,
        ]);
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Success create new user'
        ], 200);
    }

    public function updateUser($request, $uuid, $user){
        $validated = [
            'name' => $request->name,
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

}
