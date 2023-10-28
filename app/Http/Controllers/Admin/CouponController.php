<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Coupon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class CouponController extends Controller
{
    public function index(){
        try{
            $coupons = Coupon::select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date')->get();
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
            $coupon = Coupon::select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date')->where(['uuid' => $uuid])->first();
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

    public function store(Request $request): JsonResponse{
        $validate = [
            'type_coupon' => 'required|in:discount amount,percentage discount',
            'type_limit' => 'required|in:1,2|numeric',
            'code' => 'required|unique:coupons|string',
        ];

        if($request->type_coupon == 'discount amount'){
            $validate['price'] = 'required|numeric';
        }
        if($request->type_coupon == 'percentage discount'){
            $validate['discount'] = 'required|numeric|between:0,100';
        }
        if($request->type_limit == 1){
            $validate['limit'] = 'required|numeric';
        }
        if($request->type_limit == 2){
            $validate['expired_date'] = 'required';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $price = null;
        $discount = null;
        $limit = null;
        $expired_date = null;

        if($request->type_coupon == 'discount amount'){
            $price = $request->price;
        }
        if($request->type_coupon == 'percentage discount'){
            $discount = $request->discount;
        }
        if($request->type_limit == 1){
            $limit = $request->limit;
        }
        if($request->type_limit == 2){
            $expired_date = $request->expired_date;
        }

        $coupon = Coupon::create([
            'type_coupon' => $request->type_coupon,
            'type_limit' => $request->type_limit,
            'code' => $request->code,
            'price' => $price,
            'discount' => $discount,
            'limit' => $limit,
            'expired_date' => $expired_date,
        ]);

        return response()->json([
            'message' => 'Success create new coupon',
            'coupon' => [
                'uuid'          => $coupon->uuid,
                'type_coupon'   => $coupon->type_coupon,
                'type_limit'    => $coupon->type_limit,
                'code'          => $coupon->code,
                'price'         => $coupon->price,
                'discount'      => $coupon->discount,
                'limit'         => $coupon->limit,
                'expired_date'  => $coupon->expired_date,
            ]
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkCoupon = Coupon::where(['uuid' => $uuid])->first();
        if(!$checkCoupon){
            return response()->json([
                'message' => 'Data not found'
            ], 404);
        }

        $validate = [
            'type_coupon' => 'required|in:discount amount,percentage discount',
            'type_limit' => 'required|in:1,2|numeric',
            'code' => 'required|string',
        ];

        if($request->code != $checkCoupon->code){
            $validate['code'] = 'required|unique:coupons|string';
        }

        if($request->type_coupon == 'discount amount'){
            $validate['price'] = 'required|numeric';
        }
        if($request->type_coupon == 'percentage discount'){
            $validate['discount'] = 'required|numeric|between:0,100';
        }
        if($request->type_limit == 1){
            $validate['limit'] = 'required|numeric';
        }
        if($request->type_limit == 2){
            $validate['expired_date'] = 'required';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $price = null;
        $discount = null;
        $limit = null;
        $expired_date = null;

        if($request->type_coupon == 'discount amount'){
            $price = $request->price;
        }
        if($request->type_coupon == 'percentage discount'){
            $discount = $request->discount;
        }
        if($request->type_limit == 1){
            $limit = $request->limit;
        }
        if($request->type_limit == 2){
            $expired_date = $request->expired_date;
        }

        $checkCoupon->update([
            'type_coupon' => $request->type_coupon,
            'type_limit' => $request->type_limit,
            'code' => $request->code,
            'price' => $price,
            'discount' => $discount,
            'limit' => $limit,
            'expired_date' => $expired_date,
        ]);

        return response()->json([
            'message' => 'Success update coupon',
            'coupon' => [
                'uuid'          => $checkCoupon->uuid,
                'type_coupon'   => $checkCoupon->type_coupon,
                'type_limit'    => $checkCoupon->type_limit,
                'code'          => $checkCoupon->code,
                'price'         => $checkCoupon->price,
                'discount'      => $checkCoupon->discount,
                'limit'         => $checkCoupon->limit,
                'expired_date'  => $checkCoupon->expired_date,
            ]
        ], 200);
    }
}
