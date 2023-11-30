<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use App\Models\Package;

use App\Traits\Package\PackageTrait;

class PackageController extends Controller
{
    use PackageTrait;
    public function index(){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $purchased_packages = DB::table('purchased_packages')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category')
                ->where('purchased_packages.user_uuid', $user->uuid)
                ->join('packages', 'purchased_packages.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->distinct('package_uuid')
                ->get();

            $uuid_packages = [];

            foreach ($purchased_packages as $package) {
                $uuid_packages[] = $package->package_uuid;
            }

            $membership_histories = DB::table('membership_histories')
                ->select('packages.uuid as package_uuid', 'packages.name', 'packages.description', 'packages.package_type', 'packages.image', 'categories.name as category', 'membership_histories.expired_date')
                ->where('membership_histories.user_uuid', $user->uuid)
                ->join('packages', 'membership_histories.package_uuid', '=', 'packages.uuid')
                ->join('categories', 'packages.category_uuid', '=', 'categories.uuid')
                ->whereNotIn('membership_histories.package_uuid', $uuid_packages)
                ->whereDate('membership_histories.expired_date', '>', now())
                ->distinct('package_uuid')
                ->get();

            $packages = [];
            foreach ($purchased_packages as $index => $package) {
                $packages[] = [
                    'package_uuid' => $package->package_uuid,
                    'package_type' => $package->package_type,
                    'name' => $package->name,
                    'description' => $package->description,
                    'image' => $package->image,
                    'category' => $package->category,
                    'expired_date' => null,
                ];
            }

            foreach ($membership_histories as $index => $package) {
                $packages[] = [
                    'package_uuid' => $package->package_uuid,
                    'package_type' => $package->package_type,
                    'name' => $package->name,
                    'description' => $package->description,
                    'image' => $package->image,
                    'category' => $package->category,
                    'expired_date' => $package->expired_date,
                ];
            }

            return response()->json([
                'message' => 'Success get data',
                'packages' => $packages,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function allPackage(){
        return $this->getAllPackages(false);
    }

    public function show($package_type, $uuid){
        if($package_type != 'test' && $package_type != 'course'){
            return response()->json([
                'message' => 'Package type not valid',
            ], 404);
        }

        return $this->getOnePackage(false, $uuid, $package_type);
    }
}
