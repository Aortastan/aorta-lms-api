<?php

namespace App\Http\Controllers\API\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Xendit\Xendit;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\DetailTransaction;
use App\Models\User;
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

use App\Traits\Payment\XenditPaymentTrait;
use App\Traits\Package\PackageTrait;

class XenditController extends Controller
{
    use XenditPaymentTrait,PackageTrait;

    public function create(Request $request): JsonResponse{
        $validate = [
            // 'payment_method_uuid' => 'required|string',
            'packages' => 'required|array',
            'packages.*.package_uuid' => 'required|string',
            'packages.*.type_of_purchase' => 'required|string|in:lifetime,one month,three months,six months,one year',
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

        $user = JWTAuth::parseToken()->authenticate();

        return $this->buyPackages($request, $user);
    }

    public function expired(Request $request, $transaction_uuid){
        $get_transaction = Transaction::where([
            'uuid' => $transaction_uuid,
            "transaction_status" => 'pending',
        ])->first();

        if($get_transaction == null){
            return response()->json([
                'message' => "Transaction not found"
            ], 404);
        }
        $user = JWTAuth::parseToken()->authenticate();
        $this->paymentGateway = PaymentGatewaySetting::first();
        Xendit::setApiKey($this->paymentGateway->api_key);
        $params = [
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $get_transaction->transaction_amount,
            'success_redirect_url' => 'https://dev.aortastan.com/dashboard/student/transactions',
            "customer"=> [
                "given_names" => $user->name,
                "email" => $user->email,
                "mobile_number" => $user->mobile_number,
            ],
            "customer_notification_preference" => [
                "invoice_created" => [
                    "whatsapp",
                    "email",
                ],
                "invoice_reminder" => [
                    "whatsapp",
                    "email",
                ],
                "invoice_paid" => [
                    "whatsapp",
                    "email",
                ]
            ],
        ];

        try {

            $invoice = \Xendit\Invoice::create($params);

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

        $timestamp = strtotime($invoice['expiry_date']);
        $formattedDatetime = date('Y-m-d H:i:s', $timestamp);
        Transaction::where([
            'uuid' => $transaction_uuid,
        ])->update([
            'external_id' => $invoice['id'],
            'url' => $invoice['invoice_url'],
            'expiry_date' => $formattedDatetime,
        ]);

        return response()->json([
            'message' => 'Success extend invoice',
            'url' => $invoice['invoice_url'],
        ], 200);

    }

    public function webhook(Request $request){
        try{
            $transaction = Transaction::where('external_id', $request->id)->first();

            if($transaction == null){
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
            }

            $getPackages = DetailTransaction::where([
                'transaction_uuid' => $transaction->uuid,
            ])->get();

            $lifetimePakcages = [];
            $membershipPackages = [];

            foreach ($getPackages as $index => $package) {
                if($package->type_of_purchase == 'lifetime'){
                    $lifetimePakcages[] = [
                        "package_uuid" => $package->package_uuid,
                    ];
                }else{
                    $membershipPackages[] = [
                        "package_uuid" => $package->package_uuid,
                        "type_of_purchase" => $package->type_of_purchase,
                    ];
                }
            }

            if(count($lifetimePakcages) > 0){
                $this->purchasedPackages($transaction->uuid, $transaction->user_uuid, $lifetimePakcages);
            }

            if(count($membershipPackages) > 0){
                $this->membershipPackages($transaction->uuid, $transaction->user_uuid, $membershipPackages);
            }

            // $data = [
            //     "transaction_id" => $transaction->uuid,
            //     "transaction_name" => $package->name,
            //     "amount" => $transaction->transaction_amount,
            // ];
            // \Illuminate\Support\Facades\Mail::to("alifnaufalrizki27@gmail.com")->send(new \App\Mail\PackagePurchased($data));
            $transaction->transaction_status = 'settled';
            $transaction->save();
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Transaction success",
            ]);
            return response()->json([
                'message' => 'Transaction success',
            ], 200);
        }

        catch(\Exception $e){
            PaymentApiLog::create([
                'endpoint_url' => "tes",
                'method' => "tes",
                'status' => $e,
            ]);
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
