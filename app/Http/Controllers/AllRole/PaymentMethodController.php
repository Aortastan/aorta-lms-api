<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentGatewaySetting;

class PaymentMethodController extends Controller
{
    public function index(){

    }

    public function adminFee(){
        $payment = PaymentGatewaySetting::first();

        if($payment == null){
            return response()->json([
                'message' => "payment method not found",
            ]);
        }
        return response()->json([
            'message' => "success get data",
            "admin_fee" => $payment->admin_fee,
        ]);
    }
}
