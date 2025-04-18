<?php

namespace App\Http\Controllers\Pauli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Package;
use App\Models\Test;
use App\Models\PackageTest;
use Illuminate\Support\Facades\DB;

class PackageAssignmentController extends Controller
{
    public function assignToPackage(Request $request)
    {
        // Validasi request
        $validator = Validator::make($request->all(), [
            'package_uuid' => 'required|string|exists:packages,uuid',
            'test_uuid' => 'nullable|string|exists:tests,uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $packageUuid = $request->input('package_uuid');
        $testUuid = $request->input('test_uuid');

        // Cek apakah package dengan package_uuid ada
        $package = Package::find($packageUuid);

        if (!$package) {
            return response()->json([
                'error' => 'Package not found',
                'status' => "ERROR"
            ], 404);
        }

        $test = null;
        if ($testUuid) {
            // Cek apakah test dengan test_uuid ada dan title mengandung kata "pauli"
            $test = Test::find($testUuid);
            if (!$test || !preg_match('/pauli/i', $test->title)) {
                return response()->json([
                    'error' => 'Test not found or title does not contain "pauli"',
                    'status' => "ERROR"
                ], 404);
            }
        } else {
            // Cek apakah di dalam tabel "tests" sudah ada kolom yang title-nya mengandung nama "pauli" atau "koran"
            $test = Test::where('title', 'LIKE', '%pauli%')->orWhere('title', 'LIKE', '%koran%')->first();

            if (!$test) {
                // Jika belum ada, buat data baru dalam tabel tests
                $test = Test::create([
                    // 'uuid' => (string) \Str::uuid(),
                    'test_type' => 'classical',
                    'title' => 'Tes Koran/Pauli',
                    'status' => 'Published',
                    'test_category' => 'Quiz',
                ]);
            }
        }

        // Cek apakah test sudah diassign ke package
        $existingAssignment = PackageTest::where('package_uuid', $package->uuid)
            ->where('test_uuid', $test->uuid)
            ->first();

        if ($existingAssignment) {
            return response()->json([
                'message' => "Test sudah pernah di assign ke Package {$package->name}",
                'status' => "OK"
            ], 200);
        }

        // Buat data baru pada tabel pivot antara package dan test
        try {
            $packageTest = PackageTest::create([
                'package_uuid' => $package->uuid,
                'test_uuid' => $test->uuid,
            ]);

            return response()->json([
                'message' => "Tes Pauli berhasil ditambahkan ke Package {$package->name}",
                'status' => "OK"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to assign test to package',
                'details' => $e->getMessage(),
                'status' => "ERROR"
            ], 500);
        }
    }

    public function checkPackage($package_uuid)
    {
        $package = Package::find($package_uuid);
        if (!$package) {
            return response()->json([
                'error' => 'Package not found',
                'status' => "ERROR"
            ], 404);
        }

        $packageUuid = $package_uuid;

        $package = Package::find($packageUuid);

        if (!$package) {
            return response()->json([
                'error' => 'Package not found',
                'status' => "ERROR"
            ], 404);
        }

        $validTestUuids = PackageTest::where('package_uuid', $packageUuid)
            ->pluck('test_uuid');

        $hasValidTest = Test::whereIn('uuid', $validTestUuids)
            ->where(function ($query) {
                $query->where('title', 'LIKE', '%pauli%')
                    ->orWhere('title', 'LIKE', '%Pauli%')
                    ->orWhere('title', 'LIKE', '%koran%')
                    ->orWhere('title', 'LIKE', '%Koran%');
            })
            ->exists();

        if ($hasValidTest) {
            return response()->json([
                'validation' => 1,
                'message' => "Package {$package->name} valid mengandung Tes Pauli Durasi 60 Menit",
                'status' => 'OK'
            ], 200);
        } else {
            return response()->json([
                'validation' => 0,
                'message' => "Package {$package->name} tidak memiliki test yang valid",
                'status' => 'NOT OK'
            ], 200);
        }
    }

    public function unassignFromPackage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_uuid' => 'required|string|exists:packages,uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $packageUuid = $request->input('package_uuid');

        // Find the tests with titles containing "Pauli" or "Koran"
        $tests = Test::where(function ($query) {
            $query->where('title', 'LIKE', '%Pauli%')
                ->orWhere('title', 'LIKE', '%pauli%')
                ->orWhere('title', 'LIKE', '%Koran%')
                ->orWhere('title', 'LIKE', '%koran%');
        })->pluck('uuid');

        if ($tests->isEmpty()) {
            return response()->json([
                'error' => 'No tests found with titles containing "Pauli" or "Koran"',
                'status' => "ERROR"
            ], 404);
        }

        try {
            // Delete the entries in the pivot table
            $deletedCount = PackageTest::where('package_uuid', $packageUuid)
                ->whereIn('test_uuid', $tests)
                ->delete();

            if ($deletedCount == 0) {
                return response()->json([
                    'error' => 'No matching assignments found to delete',
                    'status' => "ERROR"
                ], 404);
            }

            return response()->json([
                'message' => "Tests with titles containing 'Pauli' or 'Koran' successfully unassigned from package",
                'status' => "OK"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to unassign tests from package',
                'details' => $e->getMessage(),
                'status' => "ERROR"
            ], 500);
        }
    }
}
