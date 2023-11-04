<?php

namespace App\Http\Controllers\API\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Xendit\Xendit;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\PaymentApiLog;
use App\Models\Coupon;
use App\Models\ClaimedCoupon;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use DateTime;
use DateInterval;

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
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => json_encode($validator->errors()),
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = Package::where([
            'uuid' => $request->package_uuid,
        ])->first();

        if(!$package){
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Package not foud",
            ]);
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
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Payment gateway not found",
            ]);
            return response()->json([
                'message' => 'Payment gateway not found',
            ], 422);
        }

        Xendit::setApiKey($paymentGateway->api_key);
        $user = JWTAuth::parseToken()->authenticate();

        $coupon_uuid = "";
        if(isset($request->coupon)){
            if($request->coupon){
                $coupon = Coupon::where([
                    'code' => $request->coupon,
                ])->first();

                $checkClaimedCoupon = ClaimedCoupon::where([
                    'coupon_uuid' => $coupon->uuid,
                    'user_uuid' => $user->uuid,
                ])->first();

                if($checkClaimedCoupon){
                    PaymentApiLog::create([
                        'endpoint_url' => $request->path(),
                        'method' => $request->method(),
                        'status' => "You've already redeem this coupon",
                    ]);
                    return response()->json([
                        'message' => "You've already redeem this coupon",
                    ], 422);
                }

                if(!$coupon){
                    PaymentApiLog::create([
                        'endpoint_url' => $request->path(),
                        'method' => $request->method(),
                        'status' => "Coupon not found",
                    ]);
                    return response()->json([
                        'message' => "Coupon not found",
                    ], 404);
                }
                $coupon_uuid = $coupon['uuid'];


                if($coupon->type_limit == 1){
                    // check limit
                    $checkClaimedCoupon = ClaimedCoupon::where([
                        'coupon_uuid' => $coupon->uuid,
                    ])->get();
                    if(count($checkClaimedCoupon) >= $coupon->limit){
                        PaymentApiLog::create([
                            'endpoint_url' => $request->path(),
                            'method' => $request->method(),
                            'status' => "The coupon has run out of limit",
                        ]);
                        return response()->json([
                            'message' => "The coupon has run out of limit",
                        ], 422);
                    }

                    ClaimedCoupon::create([
                        'coupon_uuid' => $coupon->uuid,
                        'user_uuid' => $user->uuid,
                        'is_used' => 1,
                    ]);
                }
                if($coupon->type_limit == 2){
                    $today = new DateTime();
                    $expiredDate = DateTime::createFromFormat('Y-m-d H:i:s', $coupon->expired_date);

                    if ($today > $expiredDate) {
                        PaymentApiLog::create([
                            'endpoint_url' => $request->path(),
                            'method' => $request->method(),
                            'status' => "The coupon has expired",
                        ]);
                        return response()->json([
                            'message' => "The coupon has expired",
                        ], 422);
                    }
                }

                if($coupon->type_coupon == 'discount amount'){
                    $amount = $amount - $coupon->price;
                    if($amount < 0){
                        $amount = 0;
                    }
                }
                if($coupon->type_coupon == 'percentage discount'){
                    $amount = ((100 - $coupon->discount) / 100) * $amount;
                }

            }
        }

        $amount = $amount + $paymentGateway->admin_fee;

        $params = [
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $amount,
        ];

        try {
            $createInvoice = \Xendit\Invoice::create($params);
        } catch (\Xendit\Exceptions\ApiException $e) {
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => json_encode($e->getMessage()),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);

        } catch (\Exception $e) {
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "An error Occured.",
            ]);
            return response()->json([
                'message' => 'An Error Occured.',
            ], 500);
        }

        $transaction = Transaction::create([
            'external_id' => $createInvoice['id'],
            'user_uuid' => $user->uuid,
            'package_uuid' => $request->package_uuid,
            'coupon_uuid' => $coupon_uuid,
            'type_of_purchase' => $request->type_of_purchase,
            'transaction_type' => $package->package_type,
            'transaction_amount' => $amount,
            'payment_method_uuid' => $request->payment_method_uuid,
            'transaction_status' => 'pending',
            'url' => $createInvoice['invoice_url'],
        ]);

        PaymentApiLog::create([
            'endpoint_url' => $request->path(),
            'method' => $request->method(),
            'status' => 'Success create invoice',
        ]);
        return response()->json([
            'message' => 'Success create invoice',
            'url' => $createInvoice['invoice_url'],
        ], 200);
    }

    public function webhook(Request $request){
        // $getInvoice = \Xendit\Invoice::retrieve($request->id);

        $transaction = Transaction::where('external_id', $request->external_id)->first();
        if(!$transaction){
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Transaction not found",
            ]);
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        if($transaction->transaction_status == 'settled'){
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Already updated data",
            ]);
            return response()->json([
                'message' => 'Already updated data',
            ], 200);
        }else{
            if($transaction->type_of_purchase == 'lifetime'){
                PurchasedPackage::create([
                    'transaction_uuid' => $transaction->uuid,
                    'user_uuid' => $transaction->user_uuid,
                    'package_uuid' => $transaction->package_uuid,
                ]);
            }else{
                $now = new DateTime();
                if($transaction->type_of_purchase == 'one month'){
                    $now->add(new DateInterval('P1M'));
                }elseif($transaction->type_of_purchase == 'three months'){
                    $now->add(new DateInterval('P3M'));
                }elseif($transaction->type_of_purchase == 'six months'){
                    $now->add(new DateInterval('P6M'));
                }
                elseif($transaction->type_of_purchase == 'one year'){
                    $now->add(new DateInterval('P1Y'));
                }
                MembershipHistory::create([
                    'transaction_uuid' => $transaction->uuid,
                    'user_uuid' => $transaction->user_uuid,
                    'package_uuid' => $transaction->package_uuid,
                    'expired_date' => $now->format('Y-m-d H:i:s'),
                ]);
            }

            $transaction->transaction_status = 'settled';
            $transaction->save();
            // Transaction::where('external_id', $request->external_id)->update([
            //     'transaction_status' => 'settled'
            // ]);
        }

        PaymentApiLog::create([
            'endpoint_url' => $request->path(),
            'method' => $request->method(),
            'status' => "Transaction success",
        ]);
        return response()->json([
            'message' => 'Transaction success',
        ], 200);
    }
}
