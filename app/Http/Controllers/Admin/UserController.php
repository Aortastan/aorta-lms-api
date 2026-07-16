<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\UserMenuAccess;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\DetailTransaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Traits\User\UserManagementTrait;
use App\Exports\StudentExport;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;
use DateTime;
use DateInterval;

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

        $purchasedUserUuids = PurchasedPackage::where('package_uuid', $packageUuid)
            ->pluck('user_uuid');
        $membershipUserUuids = MembershipHistory::where('package_uuid', $packageUuid)
            ->pluck('user_uuid');
        $userUuids = $purchasedUserUuids
            ->merge($membershipUserUuids)
            ->unique()
            ->values()
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
            $packageCounts = $this->countOwnedPackages($allUserUuids);

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

    private function countOwnedPackages(array $userUuids): array
    {
        if (empty($userUuids)) {
            return [];
        }

        $purchased = DB::table('purchased_packages')
            ->select('user_uuid', 'package_uuid')
            ->whereIn('user_uuid', $userUuids);

        $combined = DB::table('membership_histories')
            ->select('user_uuid', 'package_uuid')
            ->whereIn('user_uuid', $userUuids)
            ->union($purchased);

        return DB::query()
            ->fromSub($combined, 'combined')
            ->join('packages as p', 'p.uuid', '=', 'combined.package_uuid')
            ->select('combined.user_uuid', DB::raw('COUNT(DISTINCT combined.package_uuid) as cnt'))
            ->groupBy('combined.user_uuid')
            ->pluck('cnt', 'combined.user_uuid')
            ->toArray();
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

    public function storeStudent(Request $request): JsonResponse{
        $validator = $this->storeValidation($request);
        if($validator != null){
            return $validator;
        }

        return $this->storeUser($request, 'student');
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

    public function updateUserRole(Request $request, $uuid): JsonResponse
    {
        $actor = JWTAuth::parseToken()->authenticate();
        if (!$actor || $actor->email !== 'aortastan@gmail.com') {
            return response()->json([
                'message' => 'Hanya super admin yang dapat mengubah role user',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,student',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->email === 'aortastan@gmail.com') {
            return response()->json([
                'message' => 'Role super admin tidak dapat diubah',
            ], 422);
        }

        if ($actor->uuid === $user->uuid) {
            return response()->json([
                'message' => 'Tidak dapat mengubah role akun sendiri',
            ], 422);
        }

        if (!in_array($user->role, ['admin', 'student'])) {
            return response()->json([
                'message' => 'Hanya user dengan role student atau admin yang dapat diubah',
            ], 422);
        }

        $newRole = $request->input('role');
        if ($user->role === $newRole) {
            return response()->json([
                'message' => 'User sudah memiliki role tersebut',
            ], 422);
        }

        DB::transaction(function () use ($user, $uuid, $newRole) {
            User::where('uuid', $uuid)->update(['role' => $newRole]);

            // Bersihkan menu access lama apa pun arah perubahannya
            UserMenuAccess::where('user_uuid', $uuid)->delete();

            if ($newRole === 'admin') {
                // Default aman: hanya profil. Super admin lalu mengatur menunya lewat "Atur Menu".
                UserMenuAccess::create([
                    'user_uuid' => $uuid,
                    'menu_key' => '/dashboard/admin/profile',
                ]);
            }
        });

        return response()->json([
            'message' => $newRole === 'admin'
                ? 'Berhasil mengangkat user menjadi admin'
                : 'Berhasil menurunkan admin menjadi student',
            'data' => [
                'uuid' => $uuid,
                'role' => $newRole,
            ],
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

    public function addPackageToUser(Request $request, $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'package_uuid' => 'required|string',
            'type_of_purchase' => 'required|string|in:lifetime,one month,three months,six months,one year',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = Package::where('uuid', $request->package_uuid)->first();
        if (!$package) {
            return response()->json(['message' => 'Paket tidak ditemukan'], 404);
        }

        $typeOfPurchase = $request->type_of_purchase;
        $isLifetime = $typeOfPurchase === 'lifetime';

        $exists = $isLifetime
            ? PurchasedPackage::where('user_uuid', $uuid)
                ->where('package_uuid', $request->package_uuid)
                ->exists()
            : MembershipHistory::where('user_uuid', $uuid)
                ->where('package_uuid', $request->package_uuid)
                ->where('expired_date', '>=', now())
                ->exists();
        if ($exists) {
            return response()->json([
                'message' => $isLifetime
                    ? 'User sudah memiliki paket ini (lifetime)'
                    : 'User masih punya membership aktif untuk paket ini',
            ], 422);
        }

        $actor = JWTAuth::parseToken()->authenticate();
        $paymentMethod = PaymentGatewaySetting::first();

        try {
            $result = DB::transaction(function () use ($user, $package, $typeOfPurchase, $isLifetime, $paymentMethod) {
                $transaction = Transaction::create([
                    'external_id' => 'ADMIN-GRANT-' . Uuid::uuid4()->toString(),
                    'user_uuid' => $user->uuid,
                    'transaction_amount' => 0,
                    'payment_method_uuid' => $paymentMethod ? $paymentMethod->uuid : '-',
                    'transaction_status' => 'admin_grant',
                    'expiry_date' => null,
                    'url' => null,
                ]);

                DetailTransaction::create([
                    'transaction_uuid' => $transaction->uuid,
                    'package_uuid' => $package->uuid,
                    'type_of_purchase' => $typeOfPurchase,
                    'transaction_type' => 'admin_grant',
                    'detail_amount' => 0,
                ]);

                if ($isLifetime) {
                    $row = PurchasedPackage::create([
                        'transaction_uuid' => $transaction->uuid,
                        'package_uuid' => $package->uuid,
                        'user_uuid' => $user->uuid,
                    ]);
                    return [
                        'source' => 'lifetime',
                        'uuid' => $row->uuid,
                        'expired_date' => null,
                    ];
                }

                $expiredDate = new DateTime();
                $intervalMap = [
                    'one month' => 'P1M',
                    'three months' => 'P3M',
                    'six months' => 'P6M',
                    'one year' => 'P1Y',
                ];
                $expiredDate->add(new DateInterval($intervalMap[$typeOfPurchase]));

                $row = MembershipHistory::create([
                    'transaction_uuid' => $transaction->uuid,
                    'package_uuid' => $package->uuid,
                    'user_uuid' => $user->uuid,
                    'expired_date' => $expiredDate->format('Y-m-d H:i:s'),
                ]);
                return [
                    'source' => 'membership',
                    'uuid' => $row->uuid,
                    'expired_date' => $row->expired_date,
                ];
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menambah paket: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Berhasil menambah paket "' . $package->name . '" untuk ' . $user->name,
            'data' => [
                'user_uuid' => $user->uuid,
                'package_uuid' => $package->uuid,
                'package_name' => $package->name,
                'type_of_purchase' => $typeOfPurchase,
                'source' => $result['source'],
                'ownership_uuid' => $result['uuid'],
                'expired_date' => $result['expired_date'],
                'granted_by' => $actor->uuid ?? null,
            ],
        ], 200);
    }

    public function removePackageFromUser(Request $request, $uuid, $ownershipUuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $source = $request->query('source');
        if (!in_array($source, ['lifetime', 'membership'])) {
            return response()->json([
                'message' => 'Query param "source" harus "lifetime" atau "membership"',
            ], 422);
        }

        $row = $source === 'lifetime'
            ? PurchasedPackage::where('uuid', $ownershipUuid)->where('user_uuid', $uuid)->first()
            : MembershipHistory::where('uuid', $ownershipUuid)->where('user_uuid', $uuid)->first();

        if (!$row) {
            return response()->json([
                'message' => 'Kepemilikan paket tidak ditemukan untuk user ini',
            ], 404);
        }

        $packageUuid = $row->package_uuid;
        $package = Package::where('uuid', $packageUuid)->first();
        $row->delete();

        return response()->json([
            'message' => 'Paket "' . ($package ? $package->name : '-') . '" berhasil dihapus dari ' . $user->name,
            'data' => [
                'user_uuid' => $user->uuid,
                'package_uuid' => $packageUuid,
                'source' => $source,
                'ownership_uuid' => $ownershipUuid,
            ],
        ], 200);
    }

    public function getUserPackages(Request $request, $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->first();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $purchasedRows = PurchasedPackage::where('user_uuid', $uuid)->get()
            ->map(function ($r) {
                return [
                    'uuid' => $r->uuid,
                    'transaction_uuid' => $r->transaction_uuid,
                    'package_uuid' => $r->package_uuid,
                    'purchased_at' => $r->created_at,
                    'expired_date' => null,
                    'source' => 'lifetime',
                ];
            });
        $membershipRows = MembershipHistory::where('user_uuid', $uuid)->get()
            ->map(function ($r) {
                return [
                    'uuid' => $r->uuid,
                    'transaction_uuid' => $r->transaction_uuid,
                    'package_uuid' => $r->package_uuid,
                    'purchased_at' => $r->created_at,
                    'expired_date' => $r->expired_date,
                    'source' => 'membership',
                ];
            });
        $rows = $purchasedRows
            ->concat($membershipRows)
            ->sortByDesc('purchased_at')
            ->values();

        $packageUuids = $rows->pluck('package_uuid')->unique()->toArray();
        $packages = Package::whereIn('uuid', $packageUuids)
            ->get()
            ->keyBy('uuid');

        $data = $rows->map(function ($row) use ($packages) {
            $pkg = $packages->get($row['package_uuid']);
            if (!$pkg) {
                return null;
            }
            return [
                'uuid' => $row['uuid'],
                'transaction_uuid' => $row['transaction_uuid'],
                'package_uuid' => $row['package_uuid'],
                'purchased_at' => $row['purchased_at'],
                'expired_date' => $row['expired_date'],
                'source' => $row['source'],
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
