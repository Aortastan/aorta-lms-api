<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BannerController extends Controller
{
    public function index(){
        try{
            $banners = DB::table('banners')
                ->select('banners.uuid', 'banners.title', 'banners.subtitle', 'banners.image', 'banners.is_active')->where(['is_active' => 1])->get();

            return response()->json([
                'message' => 'Success get data',
                'banners' => $banners,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
