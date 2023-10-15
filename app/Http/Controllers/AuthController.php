<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

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
        $this->middleware('auth:api', ['except' => ['authenticate','register']]);
    }

    /**
     * Login / Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse{
        //Verify fields
        $this->validate($request,['email' => 'required|email','password'=> 'required']);
        //Verify login information
        $credentials = $request->only(['email','password']);
        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Incorrect credentials'], 401);
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

        $existingUser = User::where('email', $request->email)->first();
        if($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists',
            ], 400);
        }
        //Create a new user and return the token token token
        $user =  User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        $token = JWTAuth::fromUser($user);
        return $this->respondWithToken($token);

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

        return response()->json(['message' => 'Successfully logged out']);
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
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}
