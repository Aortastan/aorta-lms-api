<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(){
        try{
            $subcategories = Cache::remember('all_categories_with_subcategories', 1800, function () {
                return Category::with(['subcategories'])->get();
            });

            return response()->json([
                'message' => 'Success get data',
                'categories' => $subcategories,
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
            $subcategories = DB::table('subcategories')
                ->select('subcategories.uuid', 'subcategories.name')->where(['category_uuid' => $uuid])->get();

            return response()->json([
                'message' => 'Success get data',
                'subcategories' => $subcategories,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
