<?php
namespace App\Traits\Payment;

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

use App\Traits\Student\DeleteCartTrait;
use App\Traits\Coupon\CouponTrait;
use App\Traits\Package\PackageTrait;

trait XenditPaymentTrait
{
    use DeleteCartTrait, CouponTrait, PackageTrait;

    public $invoice, $paymentGateway;

    public function buyPackages($request, $user){
        $free_packages = [];
        $paid_packages = [];

        $this->paymentGateway = PaymentGatewaySetting::first();

        if(!$this->paymentGateway){
            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => "Payment gateway not found",
            ]);
            return response()->json([
                'message' => 'Payment gateway not found',
            ], 422);
        }

        $total_amount = 0;

        foreach ($request->packages as $index => $package) {
            // cek apakah package tersebut tersedia
            $getPackage = $this->checkAvailablePackage($package['package_uuid']);
            if($getPackage == null){
                return response()->json([
                    'message' => "Package not found",
                ], 404);
            }

            // cek apakah user sudah pernah membeli lifetime package tersebut
            $checkPurchasedPackage = $this->checkPurchasedPackage($request, $package['package_uuid'], $user->uuid);
            if($checkPurchasedPackage != null){
                return $checkPurchasedPackage;
            }

            $detail_amount = 0;

            if($getPackage->learner_accesibility == 'paid'){
                if($package['type_of_purchase'] == 'lifetime'){
                    $detail_amount = $getPackage->price_lifetime;
                }elseif($package['type_of_purchase'] == 'one month'){
                    $detail_amount = $getPackage->price_one_month;
                }elseif($package['type_of_purchase'] == 'three months'){
                    $detail_amount = $getPackage->price_three_months;
                }elseif($package['type_of_purchase'] == 'six months'){
                    $detail_amount = $getPackage->price_six_months;
                }elseif($package['type_of_purchase'] == 'one year'){
                    $detail_amount = $getPackage->price_one_year;
                }

                $totalDetailAmount = $detail_amount - $getPackage->discount;

                if($totalDetailAmount < 0){
                    $totalDetailAmount = 0;
                }

                $total_amount += ($detail_amount - $getPackage->discount);
                $paid_packages[] = [
                    'package_uuid' => $getPackage->uuid,
                    'transaction_type' => $getPackage->package_type,
                    'user_uuid' => $user->uuid,
                    'type_of_purchase' => $package['type_of_purchase'],
                    'detail_amount' => $detail_amount - $getPackage->discount,
                ];

            }elseif($getPackage->learner_accesibility == 'free'){
                $free_packages[] = [
                    'package_uuid' => $getPackage->uuid,
                    'transaction_type' => $getPackage->package_type,
                    'user_uuid' => $user->uuid,
                    'type_of_purchase' => 'lifetime',
                    'detail_amount' => 0,
                ];
            }
        }

        $coupon_uuid = '';
        $external_id = "-";
        $transaction_status = 'settled';
        $url = "";

        if($total_amount > 0){
            $transaction_status = 'pending';
            if($request->coupon){
                $result = $this->checkAvailableCoupon($request, $user, $total_amount);
                if (!isset($result[0])) {
                    return response()->json($result);
                }

                $coupon_uuid = $result[1];
                $total_amount = $result[0];
            }

            $total_amount = $total_amount + $this->paymentGateway->admin_fee;

            if($total_amount > 0){
                $payment = $this->payment($request, $total_amount);
                if($payment != null){ // kalau tidak null berarti error
                    return $payment;
                }

                $url = $this->invoice['invoice_url'];
                $external_id = $this->invoice['id'];
            }

            if($coupon_uuid){
                ClaimedCoupon::create([
                    'coupon_uuid' => $coupon_uuid,
                    'user_uuid' => $user->uuid,
                    'is_used' => 1,
                ]);
            }
        }

        $transaction = Transaction::create([
            'external_id' => $external_id,
            'user_uuid' => $user->uuid,
            'coupon_uuid' => $coupon_uuid,
            'transaction_amount' => $total_amount,
            'payment_method_uuid' => $this->paymentGateway->uuid,
            'transaction_status' => $transaction_status,
            'url' => $url,
        ]);

        Transaction::where([
            'uuid' => $transaction->uuid,
        ])->update([
            'updated_at' => null
        ]);

        if($total_amount <= 0){
            $lifetimePackages = [];
            $membershipPackages = [];

            foreach ($paid_packages as $key => $paid) {
                if($paid['type_of_purchase'] == "lifetime"){
                    $lifetimePackages[] = [
                        'package_uuid' => $paid['package_uuid'],
                    ];
                }else{
                    $membershipPackages[] = [
                        'type_of_purchase' => $paid['type_of_purchase'],
                        'package_uuid' => $paid['package_uuid'],
                    ];
                }
            }

            if(count($lifetimePackages) > 0){
                $this->purchasedPackages($transaction->uuid, $user->uuid, $lifetimePackages);
            }
            if(count($membershipPackages) > 0){
                $this->membershipPackages($transaction->uuid, $user->uuid, $membershipPackages);
            }
            if(count($free_packages) > 0){
                $this->purchasedPackages($transaction->uuid, $user->uuid, $free_packages);
            }
        }

            // buat detail transaksi
            $this->createDetailTransaction($transaction->uuid, $paid_packages);
            $this->createDetailTransaction($transaction->uuid, $free_packages);

            // untuk yang free otomatis sudah terbeli
            // $this->purchasedPackages($transaction->uuid, $user->uuid, $free_packages);

            // hapus cart
            $this->deleteCart($request->packages, $user->uuid);

            PaymentApiLog::create([
                'endpoint_url' => $request->path(),
                'method' => $request->method(),
                'status' => 'Success create invoice',
            ]);
            return response()->json([
                'message' => 'Success create invoice',
                'url' => $url,
            ], 200);
    }

    private function createDetailTransaction($transaction_uuid, $packages){
        $detailPackages = [];
        foreach ($packages as $index => $package) {
            $detailPackages[] = [
                'transaction_uuid' =>$transaction_uuid,
                'uuid' => Uuid::uuid4()->toString(),
                'package_uuid' => $package['package_uuid'],
                'type_of_purchase' => $package['type_of_purchase'],
                'transaction_type' => $package['transaction_type'],
                'detail_amount' => $package['detail_amount'],
            ];
        }

        if(count($detailPackages) > 0){
            DetailTransaction::insert($detailPackages);
        }
    }

    private function payment($request, $total_amount){
        Xendit::setApiKey($this->paymentGateway->api_key);
        $params = [
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $total_amount,
            'success_redirect_url' => 'https://aortastan-5a3a6.web.app/dashboard/student/transactions',
        ];

        try {

            $this->invoice = \Xendit\Invoice::create($params);
            return null;

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
    }
}
