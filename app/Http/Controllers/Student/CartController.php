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
                $cartData = [
                    'uuid' => $cart->uuid,
                    'package_uuid' => $cart->package->uuid,
                    'product_type' => $cart->product_type,
                    'qty' => $cart->qty,
                    'package' => $cart->package->name,
                    'package_image' => $cart->package->image,
                    'price_one_month' => $cart->package->price_one_month,
                    'price_three_months' => $cart->package->price_three_months,
                    'price_six_months' => $cart->package->price_six_months,
                    'price_one_year' => $cart->package->price_one_year,
                    'discount' => $cart->package->discount,
                ];
                
                // Only add price_lifetime if it's not 0
                if ($cart->package->price_lifetime != 0) {
                    $cartData['price_lifetime'] = $cart->package->price_lifetime;
                }
    
                $carts[] = $cartData;
            }
    
            $phone_status = $this->user->mobile_number ? true : false;
            $message = $phone_status ? 'Sukses mengambil data' : 'Silahkan lengkapi nomor telepon anda';
    
            return response()->json([
                'message' => $message,
                'carts' => $carts,
                'phone_status' => $phone_status,
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
                'message' => 'Validasi error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkPackage = Package::where([
            'uuid' => $request->package_uuid,
        ])->first();

        if (!$checkPackage) {
            return response()->json([
                'message' => 'Paket tidak ditemukan',
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
            'message' => 'Sukses menambahkan ke keranjang',
        ], 200);
    }

    public function delete($cart_uuid){
        try{
            Cart::
            where([
                'uuid' => $cart_uuid,
            ])->delete();

            return response()->json([
                'message' => 'Berhasil menghapus data',
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
