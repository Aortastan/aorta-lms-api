<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\Tag;
use App\Models\CourseTag;
use App\Models\TestTag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class TagController extends Controller
{
    public function index(){
        try{
            $tags = Tag::select('uuid', 'name')->get();
            return response()->json([
                'message' => 'Success get data',
                'tags' => $tags,
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
            $tag = Tag::select('uuid', 'name')->where(['uuid' => $uuid])->first();

            if(!$tag){
                return response()->json([
                    'message' => 'Data not found',
                ], 404);
            }
            return response()->json([
                'message' => 'Success get data',
                'tag' => $tag,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function store(Request $request): JsonResponse{
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:tags',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        Tag::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Success create new tag'
        ], 200);
    }

    public function update(Request $request, $uuid): JsonResponse{
        $checkTag = Tag::where(['uuid' => $uuid])->first();
        if(!$checkTag){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        if($checkTag->name != $request->name){
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:tags',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            Tag::where(['uuid' => $uuid])->update([
                'name' => $request->name,
            ]);
        }


        return response()->json([
            'message' => 'Success update tag'
        ], 200);
    }

    public function delete(Request $request, $uuid){
        $tag = Tag::where(['uuid' => $uuid])->first();
        if(!$tag){
            return response()->json([
                'message' => 'Data not found',
            ], 404);
        }

        $checkTagCourse = CourseTag::where([
            'tag_uuid' => $tag->uuid
        ])->first();

        $checkTagTest = TestTag::where([
            'tag_uuid' => $tag->uuid
        ])->first();

        if($checkTagCourse || $checkTagTest){
            return response()->json([
                'message' => 'You can\'t delete it, the tag already used in course / test'
            ], 422);
        }

        Tag::where(['uuid' => $uuid])->delete();


        return response()->json([
            'message' => 'Success delete tag'
        ], 200);
    }
}
