<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request){
        try{
            $blogs = DB::table('blogs')
                ->select('blogs.uuid as blog_uuid', 'users.name as user_name', 'categories.name as category_name', 'subcategories.name as subcategory_name', 'blogs.title', 'blogs.slug', 'blogs.body', 'blogs.image', 'blogs.status', 'blogs.seo_title', 'blogs.seo_description', 'blogs.seo_keywords','blogs.created_at', 'blogs.updated_at')
                ->join('users', 'blogs.user_uuid', '=', 'users.uuid')
                ->join('categories', 'blogs.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'blogs.subcategory_uuid', '=', 'subcategories.uuid');

            $courses = DB::table('packages')
                ->select('packages.uuid as course_uuid', 'categories.name as category_name', 'subcategories.name as subcategory_name', 'packages.name', 'packages.description', 'packages.price_lifetime', 'packages.price_one_month', 'packages.price_three_months', 'packages.price_six_months', 'packages.price_one_year', 'packages.learner_accesibility', 'packages.image','packages.discount', 'packages.is_membership', 'packages.created_at', 'packages.updated_at')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'packages.subcategory_uuid', '=', 'subcategories.uuid')
                ->where([
                    'package_type' => 'course',
                    'status' => 'Published'
                ]);

            $tryouts = DB::table('packages')
            ->select('packages.uuid as course_uuid', 'categories.name as category_name', 'subcategories.name as subcategory_name', 'packages.name', 'packages.description', 'packages.price_lifetime', 'packages.price_one_month', 'packages.price_three_months', 'packages.price_six_months', 'packages.price_one_year', 'packages.learner_accesibility', 'packages.image','packages.discount', 'packages.is_membership', 'packages.created_at', 'packages.updated_at')
            ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
            ->join('subcategories', 'packages.subcategory_uuid', '=', 'subcategories.uuid')
            ->where([
                'package_type' => 'test',
                'status' => 'Published'
            ]);

            if ($request->has('key')) {
                $key = $request->input('key');
                if($key){
                    $blogs = $blogs->where('blogs.title', 'like', '%' . $key . '%');
                    $courses = $courses->where('packages.name', 'like', '%' . $key . '%');
                    $tryouts = $tryouts->where('packages.name', 'like', '%' . $key . '%');
                }
            }

            if ($request->has('subcategory_uuid')) {
                $subcategory_uuid = $request->input('subcategory_uuid');
                if($subcategory_uuid){
                    $blogs = $blogs->where('blogs.subcategory_uuid', $subcategory_uuid);
                    $courses = $courses->where('packages.subcategory_uuid', $subcategory_uuid);
                    $tryouts = $tryouts->where('packages.subcategory_uuid', $subcategory_uuid);
                }
            }

            if ($request->has('category_uuid')) {
                $category_uuid = $request->input('category_uuid');
                if($category_uuid){
                    $blogs = $blogs->where('blogs.category_uuid', $category_uuid);
                    $courses = $courses->where('packages.category_uuid', $category_uuid);
                    $tryouts = $tryouts->where('packages.category_uuid', $category_uuid);
                }
            }

            $blogs = $blogs->get();
            $courses = $courses->get();
            $tryouts = $tryouts->get();

            return response()->json([
                'message' => 'Success get data',
                'blogs' => $blogs,
                'courses' => $courses,
                'tryouts' => $tryouts,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }
}
