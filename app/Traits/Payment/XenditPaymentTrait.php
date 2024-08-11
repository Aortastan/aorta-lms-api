<?php
namespace App\Traits\Payment;

use Xendit\Xendit;
use App\Models\Transaction;
use App\Models\Package;
use App\Models\DetailTransaction;
use App\Models\User;
use App\Models\Coupon;
use App\Models\PaymentApiLog;
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
        $list_coupon_not_restricted = [];
        $list_coupon_package = [];
        $list_coupon_category = [];
        $list_of_package_and_category = [];

        // cek semua kuponnya
        foreach ($request->coupon as $key => $code) {
            $get_coupon = Coupon::where([
                'code' => $code
            ])->first();

            if($get_coupon == null){
                return response()->json([
                    'message' => 'Coupon not found',
                ], 404);
            }

            if($get_coupon->is_restricted == 1){
                if($get_coupon->restricted_by == 'package'){
                    $list_coupon_package[] = [
                        'uuid' => $get_coupon->uuid,
                        'type_coupon' => $get_coupon->type_coupon,
                        'type_limit' => $get_coupon->type_limit,
                        'code' => $get_coupon->code,
                        'price' => $get_coupon->price,
                        'discount' => $get_coupon->discount,
                        'limit' => $get_coupon->limit,
                        'expired_date' => $get_coupon->expired_date,
                        'limit_per_user' => $get_coupon->limit_per_user,
                        'is_restricted' => $get_coupon->is_restricted,
                        'restricted_by' => $get_coupon->restricted_by,
                        'package_uuid' => $get_coupon->package_uuid,
                        'category_uuid' => $get_coupon->category_uuid,
                    ];
                }elseif($get_coupon->restricted_by == 'category'){
                    $list_coupon_category[] = [
                        'uuid' => $get_coupon->uuid,
                        'type_coupon' => $get_coupon->type_coupon,
                        'type_limit' => $get_coupon->type_limit,
                        'code' => $get_coupon->code,
                        'price' => $get_coupon->price,
                        'discount' => $get_coupon->discount,
                        'limit' => $get_coupon->limit,
                        'expired_date' => $get_coupon->expired_date,
                        'limit_per_user' => $get_coupon->limit_per_user,
                        'is_restricted' => $get_coupon->is_restricted,
                        'restricted_by' => $get_coupon->restricted_by,
                        'package_uuid' => $get_coupon->package_uuid,
                        'category_uuid' => $get_coupon->category_uuid,
                    ];
                }
            }else{
                $list_coupon_not_restricted[] = [
                    'uuid' => $get_coupon->uuid,
                    'type_coupon' => $get_coupon->type_coupon,
                    'type_limit' => $get_coupon->type_limit,
                    'code' => $get_coupon->code,
                    'price' => $get_coupon->price,
                    'discount' => $get_coupon->discount,
                    'limit' => $get_coupon->limit,
                    'expired_date' => $get_coupon->expired_date,
                    'limit_per_user' => $get_coupon->limit_per_user,
                    'is_restricted' => $get_coupon->is_restricted,
                    'restricted_by' => $get_coupon->restricted_by,
                    'package_uuid' => $get_coupon->package_uuid,
                    'category_uuid' => $get_coupon->category_uuid,
                ];
            }
        }

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

                // algoritma untuk cek code coupon yang ada package_uuid nya (code khusus pakcage)
                if($totalDetailAmount > 0){
                    if(count($list_coupon_package) > 0){
                        foreach ($list_coupon_package as $key1 => $coupon_package) {
                            if($coupon_package['package_uuid'] == $getPackage->uuid){
                                $checkClaimedCoupon = ClaimedCoupon::where([
                                    'coupon_uuid' => $coupon_package['uuid'],
                                    'user_uuid' => $user->uuid,
                                ])->get();

                                if(count($checkClaimedCoupon) >= $coupon_package['limit_per_user']){
                                    return response()->json([
                                        'message' => 'You\'ve already redeemed this coupon',
                                    ], 400);
                                }

                                if($coupon_package['type_limit'] == 1){
                                    // check limit
                                    $checkClaimedCoupon = ClaimedCoupon::where([
                                        'coupon_uuid' => $coupon_package['uuid'],
                                    ])->count();
                                    if($checkClaimedCoupon >= $coupon_package['limit']){
                                        return response()->json([
                                            'message' => 'The coupon has run out of limit',
                                        ], 400);
                                    }
                                }
                                if($coupon_package['type_limit'] == 2){
                                    $today = new DateTime();
                                    $expiredDate = DateTime::createFromFormat('Y-m-d H:i:s', $coupon_package['expired_date']);

                                    if ($today > $expiredDate) {
                                        return response()->json([
                                            'message' => 'The coupon has expired',
                                        ], 400);
                                    }
                                }

                                if($coupon_package['type_coupon'] == 'discount amount'){
                                    $totalDetailAmount = $totalDetailAmount - $coupon_package['price'];
                                    if($totalDetailAmount < 0){
                                        $totalDetailAmount = 0;
                                    }
                                }
                                if($coupon_package['type_coupon'] == 'percentage discount'){
                                    $totalDetailAmount = ((100 - $coupon_package['discount']) / 100) * $totalDetailAmount;
                                }
                            }
                        }
                    }
                }

                $list_of_package_and_category[] = [
                    "package_uuid" => $getPackage->uuid,
                    "category_uuid" => $getPackage->category_uuid,
                    "total_detail_amount" => $totalDetailAmount,
                ];

                $paid_packages[] = [
                    'package_uuid' => $getPackage->uuid,
                    'transaction_type' => $getPackage->package_type,
                    'user_uuid' => $user->uuid,
                    'type_of_purchase' => $package['type_of_purchase'],
                    'detail_amount' => $totalDetailAmount,
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

        if(count($list_coupon_category) > 0){
            foreach ($list_coupon_category as $key1 => $coupon_category) {
                $list_of_package_by_this_category= [];
                foreach ($list_of_package_and_category as $index1 => $data) {
                    if($data['category_uuid'] == $coupon_category['category_uuid']){
                        $list_of_package_by_this_category[] = $data;
                        unset($list_of_package_and_category[$index1]);
                    }
                }


                if(count($list_of_package_by_this_category) > 0){
                    $total_percategory = 0;
                    foreach ($list_of_package_by_this_category as $index2 => $list) {
                        $total_percategory += $list['total_detail_amount'];
                    }
                    $checkClaimedCoupon = ClaimedCoupon::where([
                        'coupon_uuid' => $coupon_category['uuid'],
                        'user_uuid' => $user->uuid,
                    ])->get();

                    if(count($checkClaimedCoupon) >= $coupon_category['limit_per_user']){
                        return response()->json([
                            'message' => 'You\'ve already redeemed this coupon',
                        ], 400);
                    }

                    if($coupon_category['type_limit'] == 1){
                        // check limit
                        $checkClaimedCoupon = ClaimedCoupon::where([
                            'coupon_uuid' => $coupon_category['uuid'],
                        ])->count();
                        if($checkClaimedCoupon >= $coupon_category['limit']){
                            return response()->json([
                                'message' => 'The coupon has run out of limit',
                            ], 400);
                        }
                    }
                    if($coupon_category['type_limit'] == 2){
                        $today = new DateTime();
                        $expiredDate = DateTime::createFromFormat('Y-m-d H:i:s', $coupon_category['expired_date']);

                        if ($today > $expiredDate) {
                            return response()->json([
                                'message' => 'The coupon has expired',
                            ], 400);
                        }
                    }

                    if($coupon_category['type_coupon'] == 'discount amount'){
                        $total_percategory = $total_percategory - $coupon_category['price'];
                        if($total_percategory < 0){
                            $total_percategory = 0;
                        }
                    }
                    if($coupon_category['type_coupon'] == 'percentage discount'){
                        $total_percategory = ((100 - $coupon_category['discount']) / 100) * $total_percategory;
                    }

                    $total_amount += $total_percategory;
                }
            }
        }

        foreach ($list_of_package_and_category as $index1 => $data) {
            $total_amount += $data['total_detail_amount'];
        }

        if(count($list_coupon_not_restricted) > 0){
            foreach ($list_coupon_not_restricted as $key1 => $coupon) {
                $checkClaimedCoupon = ClaimedCoupon::where([
                    'coupon_uuid' => $coupon['uuid'],
                    'user_uuid' => $user->uuid,
                ])->get();

                if(count($checkClaimedCoupon) >= $coupon['limit_per_user']){
                    return response()->json([
                        'message' => 'You\'ve already redeemed this coupon',
                    ], 400);
                }

                if($coupon['type_limit'] == 1){
                    // check limit
                    $checkClaimedCoupon = ClaimedCoupon::where([
                        'coupon_uuid' => $coupon['uuid'],
                    ])->count();
                    if($checkClaimedCoupon >= $coupon['limit']){
                        return response()->json([
                            'message' => 'The coupon has run out of limit',
                        ], 400);
                    }
                }
                if($coupon['type_limit'] == 2){
                    $today = new DateTime();
                    $expiredDate = DateTime::createFromFormat('Y-m-d H:i:s', $coupon['expired_date']);

                    if ($today > $expiredDate) {
                        return response()->json([
                            'message' => 'The coupon has expired',
                        ], 400);
                    }
                }

                if($coupon['type_coupon'] == 'discount amount'){
                    $total_amount = $total_amount - $coupon['price'];
                    if($total_amount < 0){
                        $total_amount = 0;
                    }
                }
                if($coupon['type_coupon'] == 'percentage discount'){
                    $total_amount = ((100 - $coupon['discount']) / 100) * $total_amount;
                }
            }
        }

        $coupon_uuid = '';
        $external_id = "-";
        $transaction_status = 'settled';
        $url = "";
        $expiry_date = null;

        $user = JWTAuth::parseToken()->authenticate();
        if($user->mobile_number == null){
            return response()->json([
                'message' => 'Tambahkan Nomor Handphone Validmu',
            ], 400);
        }



        if($total_amount > 0){
            $transaction_status = 'pending';
            $total_amount = $total_amount + $this->paymentGateway->admin_fee;

            if($total_amount > 0){
                $payment = $this->payment($request, $total_amount);
                if($payment != null){ // kalau tidak null berarti error
                    return $payment;
                }

                $url = $this->invoice['invoice_url'];
                $external_id = $this->invoice['id'];
                $timestamp = strtotime($this->invoice['expiry_date']);
                $formattedDatetime = date('Y-m-d H:i:s', $timestamp);
                $expiry_date = $formattedDatetime;
            }
        }

        $transaction = Transaction::create([
            'external_id' => $external_id,
            'user_uuid' => $user->uuid,
            'transaction_amount' => $total_amount,
            'payment_method_uuid' => $this->paymentGateway->uuid,
            'transaction_status' => $transaction_status,
            'expiry_date' => $expiry_date,
            'url' => $url,
        ]);

        foreach ($request->coupon as $index => $coupon) {
            $checkCoupon = Coupon::where([
                'code' => $coupon,
            ])->first();
            ClaimedCoupon::create([
                'transaction_uuid' => $transaction,
                'coupon_uuid' => $checkCoupon->uuid,
                'user_uuid' => $user->uuid,
                'is_used' => 1,
            ]);
        }

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
        $user = JWTAuth::parseToken()->authenticate();
        Xendit::setApiKey($this->paymentGateway->api_key);
        $params = [
            'external_id' => Uuid::uuid4()->toString(),
            'amount' => $total_amount,
            'success_redirect_url' => 'https://aortastan.com/dashboard/student/transactions',
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
