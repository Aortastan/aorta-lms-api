<?php

namespace App\Http\Controllers;

use App\Models\PdfAnnotations;
use Illuminate\Http\Request;
use Auth;

class PdfAnnotationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {       
        try {
            
            PdfAnnotations::updateOrCreate([
                "lesson_lecture_uuid" => $request->lesson_lecture_uuid,
                "user_uuid" => Auth::id(),
            ], [
                "lesson_lecture_uuid" => $request->lesson_lecture_uuid,
                "user_uuid" => Auth::id(),
                "annotation" => json_encode($request->annotations),
            ]);
        
            return response()->json([
                "message" => "berhasil menyimpan anotasi",
                "success" => true
            ], 200);
        } catch(Exception $e) {        
            return response()->json([
                "message" => "gagal menyimpan anotasi",
                "success" => false
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\PdfAnnotations  $pdfAnnotations
     * @return \Illuminate\Http\Response
     */
    public function show($lesson_lecture_uuid)
    {
        //
        $data = PdfAnnotations::where('lesson_lecture_uuid', $lesson_lecture_uuid)->where('user_uuid', Auth::id())->first();
        if(!$data) {
            return response()->json([
            "message" => "berhasil mengambil data anotasi",
            "success" => false,
            "data" => $data
        ]);    
        }
        return response()->json([
            "message" => "berhasil mengambil data anotasi",
            "success" => true,
            "data" => $data
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PdfAnnotations  $pdfAnnotations
     * @return \Illuminate\Http\Response
     */
    public function edit(PdfAnnotations $pdfAnnotations)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PdfAnnotations  $pdfAnnotations
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PdfAnnotations $pdfAnnotations)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PdfAnnotations  $pdfAnnotations
     * @return \Illuminate\Http\Response
     */
    public function destroy(PdfAnnotations $pdfAnnotations)
    {
        //
    }
}
