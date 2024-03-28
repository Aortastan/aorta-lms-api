<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try{
            // $headerSecretJWT = $request->header('secret');

            // if ($headerSecretJWT !== env('JWT_SECRET')) {
            //     return response()->json([
            //         'message' => 'Secret not valid',
            //     ], 401);
            // }

            if(! $user = JWTAuth::parseToken()->authenticate()){
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            if($user->role != 'student'){
                return response()->json([
                    'message' => 'Not allowed',
                ], 403);
            }
        }
        catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'token expired',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'token invalid',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'message' => 'token not found',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'message' => 'could not decode token',
            ], 401);
        }
        return $next($request);
    }
}
