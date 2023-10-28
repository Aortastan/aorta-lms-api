<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        # except for authenticate/login & register
        $this->middleware(['auth:api'], ['except' => ['authenticate','register']]);
    }

    /**
     * Login / Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse{
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        //Verify login information
        $credentials = $request->only(['email','password']);
        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Incorrect credentials'], 401);
        }
        return $this->respondWithToken($token);

    }

    /**
     * Register a user using credentials
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse{

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

        //Create a new user and return the token token token
        $user =  User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role' => 'student',
        ]);
        $user->sendEmailVerificationNotification();
        $token = JWTAuth::fromUser($user);
        return response()->json([
            'message' => 'Check your email',
            'user' => [
                'role'          => $user->role,
                'name'          => $user->name,
                'username'      => $user->username,
                'email'         => $user->email,
                'mobile_number' => $user->mobile_number,
                'gender'        => $user->gender,
                'avatar'        => $user->avatar,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ], 200);


    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    /**
     * Refresh a token.
     * Maybe implement in the future: https://github.com/tymondesigns/jwt-auth/issues/872#issuecomment-256616017
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return JsonResponse
     */
    protected function respondWithToken($token): JsonResponse
    {
        return response()->json([
            'user' => [
                'role'          => auth()->user()->role,
                'name'          => auth()->user()->name,
                'username'      => auth()->user()->username,
                'email'         => auth()->user()->email,
                'mobile_number' => auth()->user()->mobile_number,
                'gender'        => auth()->user()->gender,
                'avatar'        => auth()->user()->avatar,
            ],
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }


}
