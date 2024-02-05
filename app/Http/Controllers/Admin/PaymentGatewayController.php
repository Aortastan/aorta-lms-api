<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class PaymentGatewayController extends Controller
{
    public function index(){
        try{
            $payments = PaymentGatewaySetting::select('uuid', 'admin_fee', 'gateway_name', 'api_key', 'secret', 'is_enabled')->get();
            return response()->json([
                'message' => 'Success get data',
                'payments' => $payments,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function show(Request $request, $uuid){
        try{
            $payment = PaymentGatewaySetting::select('uuid', 'admin_fee', 'gateway_name', 'api_key', 'secret', 'is_enabled')->where(['uuid' => $uuid])->first();
            return response()->json([
                'message' => 'Success get data',
                'payment' => $payment,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse{
        $validate = [
            'admin_fee' => 'required|numeric',
            'gateway_name' => 'required|string',
            'api_key' => 'required|string',
            'secret' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = PaymentGatewaySetting::create([
            'admin_fee' => $request->admin_fee,
            'gateway_name' => $request->gateway_name,
            'api_key' => $request->api_key,
            'secret' => $request->secret,
            'is_enabled' => 1,
        ]);

        return response()->json([
            'message' => 'Success create new payment gateway',
            'payment' => [
                'uuid'          => $payment->uuid,
                'admin_fee'     => $payment->admin_fee,
                'gateway_name'  => $payment->gateway_name,
                'api_key'       => $payment->api_key,
                'secret'        => $payment->secret,
                'is_enabled'    => $payment->is_enabled,
            ]
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkPayment = PaymentGatewaySetting::where(['uuid' => $uuid])->first();
        if(!$checkPayment){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }

        $validate = [
            'admin_fee' => 'required|numeric',
            'gateway_name' => 'required|string',
            'api_key' => 'required|string',
            'secret' => 'required|string',
            'is_enabled' => 'required|boolean',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkPayment->update([
            'admin_fee' => $request->admin_fee,
            'gateway_name' => $request->gateway_name,
            'api_key' => $request->api_key,
            'secret' => $request->secret,
            'is_enabled' => $request->is_enabled,
        ]);

        return response()->json([
            'message' => 'Success update payment gateway',
            'payment' => [
                'uuid'          => $checkPayment->uuid,
                'admin_fee'     => $checkPayment->admin_fee,
                'gateway_name'  => $checkPayment->gateway_name,
                'api_key'       => $checkPayment->api_key,
                'secret'        => $checkPayment->secret,
                'is_enabled'    => $checkPayment->is_enabled,
            ]
        ], 200);
    }
}
