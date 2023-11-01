<?php

namespace App\Http\Controllers\API\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Xendit\Xendit;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;


class XenditController extends Controller
{
    public function create(Request $request): JsonResponse{
        $validate = [
            'payment_method_uuid' => 'required|string',
            'package_uuid' => 'required|string',
            'type_of_purchase' => 'required|string|in:lifetime,one month,three months,six months,one year',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = Package::where([
            'uuid' => $request->package_uuid,
        ])->first();

        if(!$package){
            return response()->json([
                'message' => 'Package not found',
            ], 404);
        }

        if($request->type_of_purchase == 'lifetime'){
            $amount = $package->price_lifetime;
        }elseif($request->type_of_purchase == 'one month'){
            $amount = $package->price_one_month;
        }elseif($request->type_of_purchase == 'three months'){
            $amount = $package->price_three_months;
        }elseif($request->type_of_purchase == 'six months'){
            $amount = $package->price_six_months;
        }elseif($request->type_of_purchase == 'one year'){
            $amount = $package->price_one_year;
        }
        $amount = ((100 - $package->discount) / 100) * $amount;

        $paymentGateway = PaymentGatewaySetting::where([
            'uuid' => $request->payment_method_uuid,
        ])->first();

        if(!$paymentGateway){
            return response()->json([
                'message' => 'Payment gateway not found',
            ], 422);
        }

        Xendit::setApiKey($paymentGateway->api_key);

        $params = [
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $amount,
        ];

        try {
            $createInvoice = \Xendit\Invoice::create($params);
        } catch (\Xendit\Exceptions\ApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An Error Occured.',
            ], 500);
        }

        if($request->type_of_purchase == 'lifetime'){
            $type_of_purchase = 'paid';
        }else{
            $type_of_purchase = 'membership';
        }

        $user = JWTAuth::parseToken()->authenticate();

        $transaction = Transaction::create([
            'uuid' => $params['external_id'],
            'user_uuid' => $user->uuid,
            'type_of_purchase' => $type_of_purchase,
            'transaction_type' => $package->package_type,
            'transaction_amount' => $amount,
            'payment_method_uuid' => $request->payment_method_uuid,
            'transaction_status' => 'pending',
            'url' => $createInvoice['invoice_url'],
        ]);

        return response()->json([
            'message' => 'Success create invoice',
            'url' => $createInvoice['invoice_url'],
            'id' => $createInvoice['id']
        ], 200);
    }

    public function webhook(Request $request){
        $getInvoice = \Xendit\Invoice::retrieve($request->id);

        $transaction = Transaction::where('uuid', $request->external_id)->first();
        if(!$transaction){
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        Transaction::where('uuid', $request->external_id)->update([
            'transaction_status' => 'settled'
        ]);

        return response()->json([
            'message' => 'Transaction success',
        ], 200);
    }
}
