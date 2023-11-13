<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Package;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class CartController extends Controller
{
    public $user;

    public function __construct(){
        $this->user = JWTAuth::parseToken()->authenticate();
    }

    public function index(){
        try{
            $getCarts = Cart::
            where([
                'user_uuid' => $this->user->uuid,
            ])->with(['package'])->get();

            $carts = [];

            foreach ($getCarts as $index => $cart) {
                $carts[] = [
                    'uuid' => $cart->uuid,
                    'product_type' => $cart->product_type,
                    'qty' => $cart->qty,
                    'package' => $cart->package->name,
                    'package_image' => $cart->package->image,
                    'price_lifetime' => $cart->package->price_lifetime,
                    'price_one_month' => $cart->package->price_one_month,
                    'price_three_months' => $cart->package->price_three_months,
                    'price_six_months' => $cart->package->price_six_months,
                    'price_one_year' => $cart->package->price_one_year,
                ];
            }

            return response()->json([
                'message' => 'Success get data',
                'carts' => $carts,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request){
        $validate = [
            'package_uuid' => 'required|string',
        ];

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkPackage = Package::where([
            'uuid' => $request->package_uuid,
        ])->first();

        if (!$checkPackage) {
            return response()->json([
                'message' => 'Package not found',
            ], 422);
        }

        $checkCart = Cart::where([
            'package_uuid' => $checkPackage->uuid,
            'user_uuid' => $this->user->uuid,
        ])->first();

        if(!$checkCart){
            Cart::create([
                'user_uuid' => $this->user->uuid,
                'package_uuid' => $checkPackage->uuid,
                'product_type' => $checkPackage->package_type,
                'qty' => 1,
            ]);
        }

        return response()->json([
            'message' => 'Success add package to cart',
        ], 422);
    }

    public function delete($cart_uuid){
        try{
            Cart::
            where([
                'uuid' => $cart_uuid,
            ])->delete();

            return response()->json([
                'message' => 'Success delete data',
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
