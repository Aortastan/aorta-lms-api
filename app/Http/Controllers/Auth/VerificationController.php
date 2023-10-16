<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\VerifiesEmails;

class VerificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function verify($id, Request $request){
        if(!$request->hasValidSignature()){
            return response()->json([
                'message' => 'Verification email fails',
            ], 400);
        }

        $user = User::find($id);
        if(!$user->hasVerifiedEmail()){
            $user->markEmailAsVerified();
        }
        return  redirect()->route('home');
    }

    public function resend(){
        if(Auth::user()->hasVerifiedEmail()){
            return response()->json([
                'message' => 'Your email already verified',
            ], 403);
        }

        Auth()->user()->sendEmailVerificationNotification();
        return response()->json([
            'message' => 'Check your email',
        ], 200);
    }
}
