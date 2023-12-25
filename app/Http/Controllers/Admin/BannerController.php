<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\Banner;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use File;

class BannerController extends Controller
{
    public function index(){
        try{
            $banners = DB::table('banners')
                ->select('banners.uuid', 'banners.title', 'banners.subtitle', 'banners.image', 'banners.is_active')->get();

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

    public function show(Request $request, $uuid){
        try{
            $banner = DB::table('banners')
                ->select('banners.uuid', 'banners.title', 'banners.subtitle', 'banners.image', 'banners.is_active')->where(['uuid' => $uuid])->first();

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

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'subtitle' => 'required|string',
            'image' => 'required|image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $request->image->store('banners', 'public');

        $validated = [
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image' => $path,
            'is_active' => 1,
        ];

        Banner::where([
            'is_active' => 1,
        ])->update([
            'is_active' => 0,
        ]);

        Banner::create($validated);

        return response()->json([
            'message' => 'Success create new banner'
        ], 200);
    }

    public function update(Request $request, $uuid){
        $checkBanner = Banner::where(['uuid' => $uuid])->first();
        if(!$checkBanner){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $validate = [
            'title' => 'required|string',
            'subtitle' => 'required|string',
            'is_active' => 'required|boolean'
        ];

        if(isset($request->image)){
            if(!is_string($request->image)){
                $validate['image'] = 'required|image';
            }
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $checkBanner->image;
        if(!is_string($request->image)){
            if (File::exists(public_path('storage/'.$checkBanner->image))) {
                File::delete(public_path('storage/'.$checkBanner->image));
            }
            if($request->image){
                $path = $request->image->store('banners', 'public');
            }
        }


        $validated = [
            'title' => $request->title,
            'subtitle' => $request->subtitle,
            'image' => $path,
            'is_active' => $request->is_active,
        ];

        if($checkBanner->is_active != $request->is_active){
            if($request->is_active == 1){
                Banner::where([
                    'is_active' => 1
                ])->update([
                    'is_active' => 0,
                ]);
            }
        }

        Banner::where(['uuid' => $uuid])->update($validated);

        if($checkBanner->is_active != $request->is_active){
            if($request->is_active == 0){
                Banner::latest()->first()->update(['is_active' => 1]);
            }
        }

        return response()->json([
            'message' => 'Success update banner'
        ], 200);
    }

    public function delete(Request $request, $uuid){
        $checkBanner = Banner::where(['uuid' => $uuid])->first();
        if(!$checkBanner){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }
        if (File::exists(public_path('storage/'.$checkBanner->image))) {
            File::delete(public_path('storage/'.$checkBanner->image));
        }

        Banner::where(['uuid' => $uuid])->delete();


        if($checkBanner->is_active == 1){
            $latestBanner = Banner::latest()->first()->update(['is_active' => 1]);
        }

        return response()->json([
            'message' => 'Success delete banner'
        ], 200);
    }
}
