<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(){
        $data["total_course"] = 50;
        $data['total_tryout'] = 40;
        return response()->json([
            'message' => 'Success get data',
            'data' => $data,
        ], 200);
    }
}
