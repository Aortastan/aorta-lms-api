<?php
namespace App\Traits\Blog;
use Illuminate\Support\Facades\DB;

trait BlogManagementTrait
{
    public function getAllBlogs($byAdmin = false, $limit=0){
        try{
            $blogs = DB::table('blogs')
                ->select('blogs.uuid', 'blogs.title', 'blogs.slug', 'blogs.body', 'blogs.image', 'blogs.status', 'blogs.seo_title', 'blogs.seo_description', 'blogs.seo_keywords', 'users.name as user_name', 'categories.name as category_name', 'subcategories.name as subcategory_name')
                ->join('users', 'blogs.user_uuid', '=', 'users.uuid')
                ->join('categories', 'blogs.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'blogs.subcategory_uuid', '=', 'subcategories.uuid');

            if($byAdmin == false){
                $blogs = $blogs->where(['blogs.status' => 1]);
            }

            if($limit > 0){
                $blogs = $blogs->limit($limit);
            }

            $blogs = $blogs->get();

            return response()->json([
                'message' => 'Success get data',
                'blogs' => $blogs,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function getBlog($byAdmin = false, $uuid=""){
        try{
            $blog = DB::table('blogs')
                ->select('blogs.uuid', 'blogs.title', 'blogs.slug', 'blogs.body', 'blogs.image', 'blogs.status', 'blogs.seo_title', 'blogs.seo_description', 'blogs.seo_keywords', 'users.name as user_name', 'categories.name as category_name', 'subcategories.name as subcategory_name', 'categories.uuid as category_uuid', 'subcategories.uuid as subcategory_uuid')
                ->join('users', 'blogs.user_uuid', '=', 'users.uuid')
                ->join('categories', 'blogs.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'blogs.subcategory_uuid', '=', 'subcategories.uuid')
                ->where('blogs.uuid', $uuid);

            if($byAdmin == false){
                $blog = $blog->where(['blogs.status' => 1]);
            }

            $blog = $blog->first();

            if(!$blog){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
            }
            return response()->json([
                'message' => 'Success get data',
                'blog' => $blog,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

}
