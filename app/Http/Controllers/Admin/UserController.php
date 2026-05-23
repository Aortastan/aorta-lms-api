<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\UserMenuAccess;
use App\Models\PurchasedPackage;
use App\Models\Package;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Traits\User\UserManagementTrait;
use App\Exports\StudentExport;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    use UserManagementTrait;

    public function __construct()
    {
        # except for authenticate/login & register
        $this->middleware(['auth:api', 'admin']);
    }

    public function indexAdmin(){

        return $this->usersByRole('admin');
    }

    public function indexInstructor(){

        return $this->usersByRole('instructor');

    }

    public function indexStudent(Request $request){
        $packageUuid = $request->query('package_uuid');
        if (!empty($packageUuid)) {
            return $this->studentsByPackage($packageUuid);
        }
        return $this->usersByRole('student');
    }

    private function studentsByPackage($packageUuid): JsonResponse
    {
        $package = Package::where('uuid', $packageUuid)->first();
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $userUuids = PurchasedPackage::where('package_uuid', $packageUuid)
            ->pluck('user_uuid')
            ->unique()
            ->toArray();

        $users = User::select(
            'uuid', 'role', 'name', 'username', 'email',
            'mobile_number', 'gender', 'avatar',
            'name_verified_at', 'name_verified_by'
        )
            ->where('role', 'student')
            ->whereIn('uuid', $userUuids)
            ->get();

        if ($users->isNotEmpty()) {
            $allUserUuids = $users->pluck('uuid')->toArray();
            $packageCounts = DB::table('purchased_packages as pp')
                ->join('packages as p', 'p.uuid', '=', 'pp.package_uuid')
                ->select('pp.user_uuid', DB::raw('COUNT(*) as cnt'))
                ->whereIn('pp.user_uuid', $allUserUuids)
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
            'filter' => [
                'package_uuid' => $package->uuid,
                'package_name' => $package->name,
            ],
            'users' => $users,
        ], 200);
    }

    public function exportStudent(){
        return Excel::download(new StudentExport, 'student_list.xlsx');
    }

    public function storeAdmin(Request $request): JsonResponse{
        $validator = $this->storeValidation($request);
        if($validator != null){
            return $validator;
        }

        return $this->storeUser($request, 'admin');
    }

    public function storeInstructor(Request $request): JsonResponse{
        $validator = $this->storeValidation($request);
        if($validator != null){
            return $validator;
        }

        return $this->storeUser($request, 'instructor');
    }

    public function updateAdmin(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'admin')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
    }

    public function updateInstructor(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'instructor')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
    }

    public function updateStudent(Request $request, $uuid): JsonResponse{
        if(!$user = $this->getUser($uuid, 'student')){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if($errors = $this->updateValidation($request, $user) != null){
            return $errors;
        }

        return $this->updateUser($request, $uuid, $user);
    }

    public function delete(Request $request, $uuid): JsonResponse{
        $user = User::where(['uuid' => $uuid])->first();

        if(!$user){
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user =  User::where(['uuid' => $uuid])->delete();
        return response()->json([
            'message' => 'Success delete user'
        ], 200);
    }

    public function verifyStudentName(Request $request, $uuid): JsonResponse
    {
        $user = User::where(['uuid' => $uuid, 'role' => 'student'])->first();
        if (!$user) {
            return response()->json(['message' => 'Student tidak ditemukan'], 404);
        }
        if (empty(trim((string) $user->name))) {
            return response()->json([
                'message' => 'Nama siswa belum diisi. Tidak bisa diverifikasi.',
            ], 422);
        }
        if ($user->name_verified_at) {
            return response()->json([
                'message' => 'Nama siswa sudah diverifikasi sebelumnya',
            ], 422);
        }

        $admin = JWTAuth::parseToken()->authenticate();
        $user->name_verified_at = now();
        $user->name_verified_by = $admin->uuid ?? null;
        $user->save();

        return response()->json([
            'message' => 'Berhasil memverifikasi nama siswa',
            'data' => [
                'uuid' => $user->uuid,
                'name_verified_at' => $user->name_verified_at,
                'name_verified_by' => $user->name_verified_by,
            ],
        ], 200);
    }

    public function unverifyStudentName(Request $request, $uuid): JsonResponse
    {
        $user = User::where(['uuid' => $uuid, 'role' => 'student'])->first();
        if (!$user) {
            return response()->json(['message' => 'Student tidak ditemukan'], 404);
        }
        $user->name_verified_at = null;
        $user->name_verified_by = null;
        $user->save();

        return response()->json([
            'message' => 'Verifikasi nama dibatalkan',
        ], 200);
    }

    public function getUserMenuAccess(Request $request, $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        $menus = UserMenuAccess::where('user_uuid', $uuid)->pluck('menu_key')->toArray();
        return response()->json([
            'message' => 'Success',
            'data' => [
                'user_uuid' => $uuid,
                'role' => $user->role,
                'menu_keys' => $menus,
            ],
        ], 200);
    }

    public function setUserMenuAccess(Request $request, $uuid): JsonResponse
    {
        $actor = JWTAuth::parseToken()->authenticate();
        if (!$actor || $actor->email !== 'aortastan@gmail.com') {
            return response()->json([
                'message' => 'Hanya super admin yang dapat mengatur menu user',
            ], 403);
        }

        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->email === 'aortastan@gmail.com') {
            return response()->json([
                'message' => 'Super admin selalu memiliki akses ke semua menu — tidak dapat diatur',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'menu_keys' => 'present|array',
            'menu_keys.*' => 'string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $keys = collect($request->input('menu_keys', []))
            ->map(function ($k) { return trim((string) $k); })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        DB::transaction(function () use ($uuid, $keys) {
            UserMenuAccess::where('user_uuid', $uuid)->delete();
            foreach ($keys as $k) {
                UserMenuAccess::create([
                    'user_uuid' => $uuid,
                    'menu_key' => $k,
                ]);
            }
        });

        return response()->json([
            'message' => 'Berhasil update menu access',
            'data' => [
                'user_uuid' => $uuid,
                'menu_keys' => $keys,
            ],
        ], 200);
    }

    public function getUserPackages(Request $request, $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $rows = PurchasedPackage::where('user_uuid', $uuid)
            ->orderBy('created_at', 'desc')
            ->get();

        $packageUuids = $rows->pluck('package_uuid')->unique()->toArray();
        $packages = Package::whereIn('uuid', $packageUuids)
            ->get()
            ->keyBy('uuid');

        $data = $rows->map(function ($row) use ($packages) {
            $pkg = $packages->get($row->package_uuid);
            if (!$pkg) {
                return null;
            }
            return [
                'uuid' => $row->uuid,
                'transaction_uuid' => $row->transaction_uuid,
                'package_uuid' => $row->package_uuid,
                'purchased_at' => $row->created_at,
                'package' => [
                    'uuid' => $pkg->uuid,
                    'name' => $pkg->name,
                    'package_type' => $pkg->package_type,
                    'image' => $pkg->image,
                    'is_membership' => $pkg->is_membership,
                    'status' => $pkg->status,
                ],
            ];
        })->filter()->values();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'user_uuid' => $uuid,
                'total' => $data->count(),
                'packages' => $data,
            ],
        ], 200);
    }
}
