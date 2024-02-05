<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Coupon;
use App\Models\Package;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class CouponController extends Controller
{
    public function index(){
        try{
            $coupons = Coupon::select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date', 'limit_per_user', 'is_restricted', 'restricted_by', 'package_uuid' , 'category_uuid')->get();
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
            $coupon = Coupon::select('uuid', 'type_coupon', 'type_limit', 'code', 'price', 'discount', 'limit', 'expired_date', 'limit_per_user', 'is_restricted', 'restricted_by', 'package_uuid' , 'category_uuid')->where(['uuid' => $uuid])->first();
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
            // 'limit_per_user' => 'required|numeric',
            // 'is_restricted' => 'required|in:1,2|numeric',
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

        // if($request->is_restricted == 1){
        //     $validate['restricted_by'] = 'required|in:package,category';
        //     if($request->restricted_by == 'package'){
        //         $validate['package_uuid'] = 'required';
        //     }elseif($request->restricted_by == 'category'){
        //         $validate['category_uuid'] = 'required';
        //     }
        // }

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

        $restricted_by = null;
        $package_uuid = null;
        $category_uuid = null;

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

        if($request->is_restricted == 1){
            $restricted_by = $request->restricted_by;
            if($request->restricted_by == 'package'){
                $checkPackage = Package::where([
                    'uuid' => $request->package_uuid
                ])->first();

                if($checkPackage == null){
                    return response()->json([
                        'message' => 'Package not found'
                    ]);
                }

                $package_uuid = $request->package_uuid;
            }elseif($request->restricted_by == 'category'){
                $checkCategory = Category::where([
                    'uuid' => $request->category_uuid
                ])->first();

                if($checkCategory == null){
                    return response()->json([
                        'message' => 'Category not found'
                    ]);
                }
                $category_uuid = $request->category_uuid;
            }
        }

        $coupon = Coupon::create([
            'type_coupon' => $request->type_coupon,
            'type_limit' => $request->type_limit,
            'code' => $request->code,
            'price' => $price,
            'discount' => $discount,
            'limit' => $limit,
            'expired_date' => $expired_date,
            'limit_per_user' => 1,
            'is_restricted' => 0,
            // 'restricted_by' => $restricted_by,
            // 'package_uuid' => $package_uuid,
            // 'category_uuid' => $category_uuid,
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
                // 'limit_per_user' => $coupon->limit_per_user,
                // 'is_restricted' => $coupon->is_restricted,
                // 'restricted_by' => $coupon->restricted_by,
                // 'package_uuid' => $coupon->package_uuid,
                // 'category_uuid' => $coupon->category_uuid,
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
            // 'limit_per_user' => 'required|numeric',
            // 'is_restricted' => 'required|in:1,2|numeric',
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

        // if($request->is_restricted == 1){
        //     $validate['restricted_by'] = 'required|in:package,category';
        //     if($request->restricted_by == 'package'){
        //         $validate['package_uuid'] = 'required';
        //     }elseif($request->restricted_by == 'category'){
        //         $validate['category_uuid'] = 'required';
        //     }
        // }

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

        $restricted_by = null;
        $package_uuid = null;
        $category_uuid = null;

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

        // if($request->is_restricted == 1){
        //     $restricted_by = $request->restricted_by;
        //     if($request->restricted_by == 'package'){
        //         $checkPackage = Package::where([
        //             'uuid' => $request->package_uuid
        //         ])->first();

        //         if($checkPackage == null){
        //             return response()->json([
        //                 'message' => 'Package not found'
        //             ]);
        //         }
        //         $package_uuid = $request->package_uuid;
        //     }elseif($request->restricted_by == 'category'){
        //         $checkCategory = Category::where([
        //             'uuid' => $request->category_uuid
        //         ])->first();

        //         if($checkCategory == null){
        //             return response()->json([
        //                 'message' => 'Category nto found'
        //             ]);
        //         }
        //         $category_uuid = $request->category_uuid;
        //     }
        // }

        $checkCoupon->update([
            'type_coupon' => $request->type_coupon,
            'type_limit' => $request->type_limit,
            'code' => $request->code,
            'price' => $price,
            'discount' => $discount,
            'limit' => $limit,
            'expired_date' => $expired_date,
            'limit_per_user' => 1,
            'is_restricted' => 0,
            // 'restricted_by' => $restricted_by,
            // 'package_uuid' => $package_uuid,
            // 'category_uuid' => $category_uuid,
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
                'limit_per_user' => $checkCoupon->limit_per_user,
                'is_restricted' => $checkCoupon->is_restricted,
                'restricted_by' => $checkCoupon->restricted_by,
                'package_uuid' => $checkCoupon->package_uuid,
                'category_uuid' => $checkCoupon->category_uuid,
            ]
        ], 200);
    }
}
