<?php
namespace App\Traits\Student;

use App\Models\PackageTest;
use App\Models\PurchasedPackage;
use App\Models\MembershipHistory;
use App\Models\StudentTryout;
use App\Models\TryoutSegment;
use App\Models\TryoutSegmentTest;

trait TryoutPackageScopeTrait
{
    public function tryoutUuidOfSegmentTest($segmentTestUuid)
    {
        $segmentTest = TryoutSegmentTest::where('uuid', $segmentTestUuid)->first();
        if (!$segmentTest) {
            return null;
        }

        $segment = TryoutSegment::where('uuid', $segmentTest->tryout_segment_uuid)->first();

        return $segment ? $segment->tryout_uuid : null;
    }

    public function tryoutPackageUuids($tryoutUuid)
    {
        return PackageTest::where('test_uuid', $tryoutUuid)
            ->orderBy('id')
            ->pluck('package_uuid')
            ->unique()
            ->values()
            ->all();
    }

    public function userTryoutPackageUuids($userUuid, $tryoutUuid, $activeOnly = false)
    {
        $packageUuids = $this->tryoutPackageUuids($tryoutUuid);
        if (count($packageUuids) == 0) {
            return [];
        }

        $purchased = PurchasedPackage::where('user_uuid', $userUuid)
            ->whereIn('package_uuid', $packageUuids)
            ->pluck('package_uuid')
            ->all();

        $membershipQuery = MembershipHistory::where('user_uuid', $userUuid)
            ->whereIn('package_uuid', $packageUuids);
        if ($activeOnly) {
            $membershipQuery->whereDate('expired_date', '>', now());
        }
        $memberships = $membershipQuery->pluck('package_uuid')->all();

        $owned = array_merge($purchased, $memberships);

        return array_values(array_filter($packageUuids, function ($packageUuid) use ($owned) {
            return in_array($packageUuid, $owned);
        }));
    }

    public function resolveTryoutPackageUuid($userUuid, $tryoutUuid, $requestedPackageUuid = null)
    {
        if (!$tryoutUuid) {
            return $requestedPackageUuid ?: null;
        }

        $userPackages = $this->userTryoutPackageUuids($userUuid, $tryoutUuid);
        if ($requestedPackageUuid && in_array($requestedPackageUuid, $userPackages)) {
            return $requestedPackageUuid;
        }

        $activePackages = $this->userTryoutPackageUuids($userUuid, $tryoutUuid, true);
        if (count($activePackages) == 0) {
            if ($requestedPackageUuid) {
                return $requestedPackageUuid;
            }
            $packageUuids = $this->tryoutPackageUuids($tryoutUuid);
            return $packageUuids[0] ?? null;
        }

        if (count($activePackages) > 1) {
            $segmentTestUuids = TryoutSegmentTest::join('tryout_segments', 'tryout_segments.uuid', '=', 'tryout_segment_tests.tryout_segment_uuid')
                ->where('tryout_segments.tryout_uuid', $tryoutUuid)
                ->pluck('tryout_segment_tests.uuid')
                ->all();

            $lastUsed = StudentTryout::where('user_uuid', $userUuid)
                ->whereIn('package_test_uuid', $segmentTestUuids)
                ->whereIn('package_uuid', $activePackages)
                ->orderBy('id', 'desc')
                ->value('package_uuid');

            if ($lastUsed) {
                return $lastUsed;
            }
        }

        return $activePackages[0];
    }

    public function scopeStudentTryoutsToPackage($query, $userUuid, $tryoutUuid, $packageUuid)
    {
        if (!$packageUuid || !$tryoutUuid) {
            return $query;
        }

        $otherPackages = array_values(array_diff($this->userTryoutPackageUuids($userUuid, $tryoutUuid), [$packageUuid]));
        if (count($otherPackages) == 0) {
            return $query;
        }

        return $query->where(function ($q) use ($otherPackages) {
            $q->whereNull('student_tryouts.package_uuid')
                ->orWhereNotIn('student_tryouts.package_uuid', $otherPackages);
        });
    }
}
