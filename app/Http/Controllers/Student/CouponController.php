<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ClaimedCoupon;
use App\Models\Coupon;

use Tymon\JWTAuth\Facades\JWTAuth;

class CouponController extends Controller
{
    public function redeem(Request $request){
        $validate = [
            'code' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = JWTAuth::parseToken()->authenticate();

        try{
            $coupon = Coupon::where([
                'code' => $request->code,
            ])->first();

            if($coupon == null){
                throw new \Exception("Code not found");
            }

            $checkClaimedCoupon = ClaimedCoupon::where([
                'coupon_uuid' => $coupon->uuid,
                'user_uuid' => $user->uuid,
            ])->first();

            if($checkClaimedCoupon){
                throw new \Exception("You've already redeemed this coupon");
            }

            if($coupon->type_limit == 1){
                // check limit
                $checkClaimedCoupon = ClaimedCoupon::where([
                    'coupon_uuid' => $coupon->uuid,
                ])->count();
                if($checkClaimedCoupon >= $coupon->limit){
                    throw new \Exception("The coupon has run out of limit");
                }
            }
            if($coupon->type_limit == 2){
                $today = new DateTime();
                $expiredDate = DateTime::createFromFormat('Y-m-d H:i:s', $coupon->expired_date);

                if ($today > $expiredDate) {
                    throw new \Exception("The coupon has expired");
                }
            }

            $discount = $coupon->discount;
            $amount = $coupon->price;

           return response()->json([
            'type_coupon' => $coupon->type_coupon,
            'discount_percentage' => $discount,
            'amount' => $amount,
           ]);

        }catch (\Exception $e) {
            // Tangkap exception dan kirimkan pesan kesalahan
            return ['message' => $e->getMessage()];
        }
    }
}
