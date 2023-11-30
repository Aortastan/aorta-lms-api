<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{
    public function index(){
        try{
            $getCoupons = Coupon::
                select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date')
                ->with(['claimed'])
                ->get();

            $coupons = [];
            foreach ($getCoupons as $index => $coupon) {
                if($coupon->type_limit == 1){
                    if(count($coupon->claimed) < $coupon->limit){
                        $coupons[] = $coupon;
                    }
                }
                elseif($coupon->type_limit == 2){
                    // Check if the coupon has expired
                    $expiredDate = \Carbon\Carbon::parse($coupon->expired_date);
                    $isExpired = now()->gt($expiredDate);

                    // If the coupon is not expired, you can process it further
                    if (!$isExpired) {
                        // Add the coupon to your $coupons array or perform other actions
                        $coupons[] = $coupon;
                    }
                }
            }

            return response()->json([
                'message' => 'Success get data',
                'coupons' => $coupons,
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
            $coupon = Coupon::
            select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date')
            ->first();

            if(!$coupon){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
            }
            return response()->json([
                'message' => 'Success get data',
                'coupon' => $coupon,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
