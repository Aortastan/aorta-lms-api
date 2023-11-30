<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Traits\User\UserManagementTrait;

class UserController extends Controller
{
    use UserManagementTrait;

    public function __construct()
    {
        # except for authenticate/login & register
        $this->middleware(['auth:api', 'admin']);
    }

    public function indexAdmin(){

        return $this->usersByRole('admin');
    }

    public function indexInstructor(){

        return $this->usersByRole('instructor');

    }

    public function indexStudent(){

        return $this->usersByRole('student');

    }

    public function storeAdmin(Request $request): JsonResponse{
        $validator = $this->storeValidation($request);
        if($validator != null){
            return $validator;
        }

        return $this->storeUser($request, 'admin');
    }

    public function storeInstructor(Request $request): JsonResponse{
        $validator = $this->storeValidation($request);
        if($validator != null){
            return $validator;
        }

        return $this->storeUser($request, 'instructor');
    }

    public function updateAdmin(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'admin')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
    }

    public function updateInstructor(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'instructor')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
    }

    public function updateStudent(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'student')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
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
