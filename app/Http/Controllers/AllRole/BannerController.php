<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BannerController extends Controller
{
    public function index(){
        try{
            $banner = DB::table('banners')
                ->select('banners.uuid', 'banners.title', 'banners.subtitle', 'banners.image', 'banners.is_active')->where(['is_active' => 1])->first();

            return response()->json([
                'message' => 'Success get data',
                'banner' => $banner,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
