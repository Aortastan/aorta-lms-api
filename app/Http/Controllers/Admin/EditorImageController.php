<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EditorImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EditorImageController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'upload' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp|max:' . EditorImage::MAX_UPLOAD_KB,
        ], [
            'upload.required' => 'Gambar wajib diunggah',
            'upload.file' => 'Gambar gagal diunggah',
            'upload.mimes' => 'Format gambar harus JPG, PNG, GIF, WEBP, atau BMP',
            'upload.max' => 'Ukuran gambar maksimal ' . (EditorImage::MAX_UPLOAD_KB / 1024) . ' MB',
            'upload.uploaded' => 'Gambar gagal diunggah, pastikan ukurannya maksimal ' . (EditorImage::MAX_UPLOAD_KB / 1024) . ' MB',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => ['message' => $validator->errors()->first()]], 422);
        }

        $stored = EditorImage::store(file_get_contents($request->file('upload')->getRealPath()));
        if (!$stored) {
            return response()->json(['error' => ['message' => 'File bukan gambar yang valid']], 422);
        }

        return response()->json(['url' => $stored['url']]);
    }
}
