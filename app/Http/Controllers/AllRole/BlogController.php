<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Traits\Blog\BlogManagementTrait;

class BlogController extends Controller
{
    use BlogManagementTrait;

    public function index(){
        return $this->getAllBlogs();
    }

    public function limit($number_of_limit){
        return $this->getAllBlogs(false, $number_of_limit);
    }

    public function show(Request $request, $uuid){
        return $this->getBlog(false, $uuid);
    }
}
