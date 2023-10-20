<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(){
        $data["total_student"] = 50;
        $data['course_sold'] = 40;
        $data['tryout_sold'] = 23;
        $data['revenue'] = 15000000;
        return response()->json([
            'message' => 'Success get data',
            'data' => $data,
        ], 200);
    }
}
