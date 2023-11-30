<?php
namespace App\Traits\Coupon;
use App\Models\ClaimedCoupon;
use App\Models\Coupon;

trait CouponTrait
{
    public function checkCoupon($code="", $user="")
    {
        try{
            $coupon = Coupon::where([
                'code' => $code,
            ])->first();

            if($coupon == null){
                throw new \Exception("Coupon not found");
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

            return
            [
                'success' => true,
                'coupon_uuid'=>$coupon->uuid,
                'type_coupon' => $coupon->type_coupon,
                'discount_percentage' => $discount,
                'amount' => $amount,
            ];

            if($coupon->type_coupon == 'discount amount'){
                $total_amount = $total_amount - $coupon->price;
                if($total_amount < 0){
                    $total_amount = 0;
                }
            }
            if($coupon->type_coupon == 'percentage discount'){
                $total_amount = ((100 - $coupon->discount) / 100) * $total_amount;
            }

            return [$total_amount, $coupon['uuid']];

        }catch (\Exception $e) {
            // Tangkap exception dan kirimkan pesan kesalahan
            return ['message' => $e->getMessage(), 'success' => false];
        }
    }

    public function checkAvailableCoupon($request, $user, $total_amount){
        $checkCoupon = $this->checkCoupon($request->coupon, $user);

        if($checkCoupon['success'] == false){
            return $checkCoupon;
        }
        if($checkCoupon['type_coupon'] == 'discount amount'){
            $total_amount = $total_amount - $checkCoupon['amount'];
            if($total_amount < 0){
                $total_amount = 0;
            }
        }
        if($checkCoupon['type_coupon'] == 'percentage discount'){
            $total_amount = ((100 - $checkCoupon['discount_percentage']) / 100) * $total_amount;
        }

        return [$total_amount, $checkCoupon['coupon_uuid']];
    }

}
