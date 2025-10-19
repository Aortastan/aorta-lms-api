<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

use App\Traits\Coupon\CouponTrait;

class CouponController extends Controller
{
    use CouponTrait;

    public function redeem(Request $request)
    {
        // $validate = [
        //     'code' => 'required|string',
        // ];

        $validate = [
            'packages' => 'required|array',
            'packages.*.package_uuid' => 'required|string',
            'packages.*.type_of_purchase' => 'required|string|in:lifetime,one month,three months,six months,one year',
        ];

        $count = array_count_values($request->coupon);
        $isDuplicate = isset($count[$request->selectedCoupon]) && $count[$request->selectedCoupon] > 1;
        if ($isDuplicate) {
            return response()->json([
                'message' => 'Coupon ' . $request->selectedCoupon . ' already applied',
            ], 422);
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = JWTAuth::parseToken()->authenticate();

        return $this->countDiscount($request, $user);
        // return response()->json($this->checkCoupon($request->code, $user));
    }
}
