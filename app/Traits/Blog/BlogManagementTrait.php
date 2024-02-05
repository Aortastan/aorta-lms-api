<?php
namespace App\Traits\Blog;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Subcategory;

trait BlogManagementTrait
{
    public function getAllBlogs($byAdmin = false, $limit=0, $request=""){
        try{
            $blogs = DB::table('blogs')
                ->select('blogs.uuid', 'blogs.title', 'blogs.slug', 'blogs.body', 'blogs.image', 'blogs.status', 'blogs.seo_title', 'blogs.seo_description', 'blogs.seo_keywords', 'users.name as user_name', 'categories.name as category_name', 'subcategories.name as subcategory_name')
                ->join('users', 'blogs.user_uuid', '=', 'users.uuid')
                ->join('categories', 'blogs.category_uuid', '=', 'categories.uuid')
                ->join('subcategories', 'blogs.subcategory_uuid', '=', 'subcategories.uuid');

            if($byAdmin == false){
                $blogs = $blogs->where(['blogs.status' => 1]);
            }

            if($request){
                if ($request->has('category')) {
                    $category_name = $request->input('category');
                    if($category_name){
                        $category = Category::where([
                            'name' => $category_name
                        ])->first();
                        $blogs = $blogs->where('blogs.category_uuid', $category->uuid);
                    }
                }
                if ($request->has('subcategory')) {
                    $subcategory_name = $request->input('subcategory');
                    if($subcategory_name){
                        $subcategory = Subcategory::where([
                            'name' => $subcategory_name
                        ])->first();
                        $blogs = $blogs->where('blogs.subcategory_uuid', $subcategory->uuid);
                    }
                }
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
