<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMenuAccess;
use App\Models\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
        // $this->middleware(['auth:api'], ['except' => ['authenticate','register', 'forgotPassword']]);
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
        $user = User::where('email', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Incorrect credentials'], 401);
        }

        // Check if there is already an active session on a different device (except for admin)
        if ($user->role !== 'admin' && $user->active_token && $user->active_device_id) {
            $requestDeviceId = $request->input('device_id');
            if ($user->active_device_id !== $requestDeviceId) {
                $isIdle = false;
                if ($user->last_activity_at) {
                    $isIdle = \Carbon\Carbon::parse($user->last_activity_at)->addMinutes(5)->isPast();
                }

                if (!$isIdle) {
                    try {
                        if (JWTAuth::setToken($user->active_token)->check()) {
                            $activeDeviceName = $user->active_device_name ?: 'Device Lain';
                            return response()->json([
                                'message' => "Anda sudah login di device lain ({$activeDeviceName}), silahkan logout terlebih dahulu di device tersebut."
                            ], 400);
                        }
                    } catch (\Exception $e) {
                        // Token is invalid/expired, continue to login
                    }
                } else {
                    // Invalidate the old token since the device is idle
                    try {
                        JWTAuth::setToken($user->active_token)->invalidate();
                    } catch (\Exception $e) {
                        // Ignored
                    }
                }
            }
        }

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['message' => 'Incorrect credentials'], 401);
        }
        if($user->email_verified_at === null) {
            $user->sendEmailVerificationNotification();
            return response()->json(['message' => 'Please verify your email first'], 403);
        }

        // Save active session token, device id and device name
        $user->update([
            'active_token' => $token,
            'active_device_id' => $request->input('device_id'),
            'active_device_name' => $request->input('device_name'),
        ]);

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
            'email_verified_at' => now(),
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
            'expires_in' => auth()->factory()->getTTL() * 4320
        ], 200);


    }

    public function forgotPassword(Request $request): JsonResponse{
        $validate = [
            'email' => 'required|email',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'Success send email',
        ], 200);
    }

    public function resetPassword(Request $request){
        $validate = [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|same:password_confirmation',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkToken = PasswordReset::where('email', $request->email)->first();

        if (!$checkToken || !Hash::check($request->token, $checkToken->token)) {
            return response()->json([
                'message' => 'Credential not valid',
            ], 422);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        PasswordReset::where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Success',
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

    public function botSso(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $isSuperAdmin = $user->email === 'aortastan@gmail.com';
        $hasBotAccess = $isSuperAdmin || (
            $user->role === 'admin' &&
            UserMenuAccess::where('user_uuid', $user->uuid)
                ->where('menu_key', '/dashboard/admin/bot')
                ->exists()
        );
        if (!$hasBotAccess) {
            return response()->json(['message' => 'Tidak punya akses bot'], 403);
        }
        // Pakai config() (bukan env() langsung) supaya tetap terbaca walau config di-cache.
        $secret = config('services.bot_sso_secret');
        if (!$secret) {
            return response()->json(['message' => 'BOT_SSO_SECRET belum di-set di server'], 500);
        }
        $exp = (int) round(microtime(true) * 1000) + 60000; // berlaku 60 detik
        $payload = (string) $exp;
        $sig = hash_hmac('sha256', $payload, $secret);
        return response()->json([
            'message' => 'Success',
            'data' => ['token' => $payload . '.' . $sig],
        ], 200);
    }

    public function myMenuAccess(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $menus = UserMenuAccess::where('user_uuid', $user->uuid)->pluck('menu_key')->toArray();
        return response()->json([
            'message' => 'Success',
            'data' => [
                'user_uuid' => $user->uuid,
                'role' => $user->role,
                'menu_keys' => $menus,
            ],
        ], 200);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $user = auth()->user();
        if ($user) {
            $user->update([
                'active_token' => null,
                'active_device_id' => null,
                'active_device_name' => null,
            ]);
        }
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
        $user = auth()->user();
        if ($user) {
            $user->update([
                'active_token' => $token,
            ]);
        }

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

    public function ping(): JsonResponse
    {
        $user = auth()->user();
        if ($user) {
            $user->last_activity_at = now();
            $user->save();
            return response()->json(['message' => 'pong'], 200);
        }
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
}
