<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\Category;
use App\Models\Blog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Tymon\JWTAuth\Facades\JWTAuth;
use File;
use App\Traits\Blog\BlogManagementTrait;

class BlogController extends Controller
{
    use BlogManagementTrait;

    public function index(){
        return $this->getAllBlogs(true);
    }

    public function show(Request $request, $uuid){
        return $this->getBlog(true, $uuid);
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'category_uuid' => 'required|string',
            'slug' => 'required|string|unique:blogs',
            'body' => 'required|string',
            'image' => 'required|image',
            'seo_title' => 'required|string',
            'seo_description' => 'required|string',
            'seo_keywords' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkCategory = Category::where('uuid', $request->category_uuid)->first();
        if(!$checkCategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => "Category not found",
            ], 422);
        }

        $path = $request->image->store('blogs', 'public');

        $user = JWTAuth::parseToken()->authenticate();

        $validated = [
            'user_uuid' => $user->uuid,
            'title' => $request->title,
            'category_uuid' => $request->category_uuid,
            'slug' => $request->slug,
            'body' => $request->body,
            'image' => $path,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
        ];

        Blog::create($validated);

        return response()->json([
            'message' => 'Success create new blog'
        ], 200);
    }

    public function update(Request $request, $uuid){
        $checkBlog = Blog::where(['uuid' => $uuid])->first();
        if(!$checkBlog){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $validate = [
            'title' => 'required|string',
            'category_uuid' => 'required|string',
            'slug' => 'required|string',
            'body' => 'required|string',
            'image' => 'required',
            'seo_title' => 'string',
            'seo_description' => 'string',
            'seo_keywords' => 'string',
            'status' => 'required|boolean'
        ];

        if(isset($request->slug)){
            if($request->slug != $checkBlog->slug){
                $validate['slug'] = 'required|string|unique:blogs';
            }
        }

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

        $checkCategory = Category::where('uuid', $request->category_uuid)->first();
        if(!$checkCategory){
            return response()->json([
                'message' => 'Validation failed',
                'errors' => "Category not found",
            ], 422);
        }
        $path = $checkBlog->image;
        if(!is_string($request->image)){
            if (File::exists(public_path('storage/'.$checkBlog->image))) {
                File::delete(public_path('storage/'.$checkBlog->image));
            }
            $path = $request->image->store('blogs', 'public');
        }


        $validated = [
            'title' => $request->title,
            'category_uuid' => $request->category_uuid,
            'slug' => $request->slug,
            'body' => $request->body,
            'image' => $path,
            'seo_title' => $request->seo_title,
            'seo_description' => $request->seo_description,
            'seo_keywords' => $request->seo_keywords,
            'status' => $request->status,
        ];

        Blog::where(['uuid' => $uuid])->update($validated);

        return response()->json([
            'message' => 'Success update blog'
        ], 200);
    }

    public function delete(Request $request, $uuid){
        $checkBlog = Blog::where(['uuid' => $uuid])->first();
        if(!$checkBlog){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }
        if (File::exists(public_path('storage/'.$checkBlog->image))) {
            File::delete(public_path('storage/'.$checkBlog->image));
        }

        Blog::where(['uuid' => $uuid])->delete();


        return response()->json([
            'message' => 'Success delete blog'
        ], 200);
    }
}
