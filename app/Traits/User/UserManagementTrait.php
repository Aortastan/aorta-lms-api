<?php
namespace App\Traits\User;
use App\Models\User;
use App\Models\PurchasedPackage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

trait UserManagementTrait
{
    public function usersByRole($role)
    {
        try{
            $users = User::select(
                'uuid', 'role', 'name', 'username', 'email',
                'mobile_number', 'gender', 'avatar',
                'name_verified_at', 'name_verified_by'
            )->where(['role' => $role])->get();

            if ($role === 'student' && $users->isNotEmpty()) {
                $userUuids = $users->pluck('uuid')->toArray();

                // Hanya hitung purchased_packages yang package-nya masih ada (skip orphan)
                $packageCounts = DB::table('purchased_packages as pp')
                    ->join('packages as p', 'p.uuid', '=', 'pp.package_uuid')
                    ->select('pp.user_uuid', DB::raw('COUNT(*) as cnt'))
                    ->whereIn('pp.user_uuid', $userUuids)
                    ->groupBy('pp.user_uuid')
                    ->pluck('cnt', 'pp.user_uuid')
                    ->toArray();

                $users = $users->map(function ($u) use ($packageCounts) {
                    $u->packages_count = $packageCounts[$u->uuid] ?? 0;
                    return $u;
                });
            }

            return response()->json([
                'message' => 'Success get data',
                'users' => $users,
            ], 200);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function storeValidation($request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'username' => 'required|unique:users',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return null;
    }

    public function getUser($uuid, $role){
        $user = User::where(['uuid' => $uuid, 'role' => $role])->first();
        return $user;
    }

    public function updateValidation($request, $user){
        $validate = [
            'name' => 'required',
        ];

        if($user->email != $request->email){
            $validate['email'] = 'required|email|unique:users';
        }

        if($user->username != $request->username){
            $validate['username'] = 'required|unique:users';
        }

        $validator = Validator::make($request->all(), $validate);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return null;
    }


    public function storeUser($request, $role){
        $user =  User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role' => $role,
        ]);
        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Success create new user'
        ], 200);
    }

    public function updateUser($request, $uuid, $user){
        $validated = [];

        // Lock name kalau sudah diverifikasi
        if ($user->name_verified_at && $user->name !== $request->name) {
            return response()->json([
                'message' => 'Nama sudah diverifikasi dan tidak dapat diubah',
            ], 422);
        }

        $validated['name'] = $request->name;

        if($user->email != $request->email){
            $validated['email'] = $request->email;
        }

        if($user->username != $request->username){
            $validated['username'] = $request->username;
        }

        User::where(['uuid' => $uuid])->update($validated);

        return response()->json([
            'message' => 'Success update user'
        ], 200);
    }

}
