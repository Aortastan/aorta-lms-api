<?php

namespace App\Services;

use App\Models\UserPresence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Presence
{
    const CATEGORY_CACHE_KEY = 'presence_category_online';
    const CATEGORY_CACHE_SECONDS = 30;

    const ACTIVITY_LABELS = [
        'browsing' => 'Membuka web',
        'tryout' => 'Mengerjakan Tryout',
        'quiz' => 'Mengerjakan Kuis',
        'posttest' => 'Mengerjakan Pretest/Posttest',
        'pauli' => 'Mengerjakan Tes Pauli',
    ];

    public static function ownedPackages(array $userUuids)
    {
        $purchased = DB::table('purchased_packages')
            ->select('user_uuid', 'package_uuid')
            ->whereIn('user_uuid', $userUuids);

        $membership = DB::table('membership_histories')
            ->select('user_uuid', 'package_uuid')
            ->whereIn('user_uuid', $userUuids)
            ->where('expired_date', '>', now());

        return $purchased->union($membership);
    }

    public static function ownedPackageUuids(string $userUuid, array $among = []): array
    {
        $query = DB::query()->fromSub(self::ownedPackages([$userUuid]), 'owned');
        if ($among) {
            $query->whereIn('owned.package_uuid', $among);
        }

        return $query->distinct()->pluck('owned.package_uuid')->all();
    }

    public static function userCategories(array $userUuids): Collection
    {
        if (!$userUuids) {
            return collect();
        }

        return DB::query()
            ->fromSub(self::ownedPackages($userUuids), 'owned')
            ->join('packages', 'packages.uuid', '=', 'owned.package_uuid')
            ->join('categories', 'categories.uuid', '=', 'packages.category_uuid')
            ->select('owned.user_uuid', 'categories.uuid as category_uuid', 'categories.name as category_name')
            ->distinct()
            ->get();
    }

    public static function onlineUsersByCategory(): array
    {
        return Cache::remember(self::CATEGORY_CACHE_KEY, self::CATEGORY_CACHE_SECONDS, function () {
            $online = UserPresence::online()->pluck('user_uuid')->all();

            $map = [];
            foreach (self::userCategories($online) as $row) {
                $map[$row->category_uuid][] = $row->user_uuid;
            }

            return $map;
        });
    }

    public static function describeTests(Collection $presences): array
    {
        $refs = function ($activity) use ($presences) {
            return $presences->where('activity', $activity)->pluck('test_ref_uuid')->filter()->unique()->values()->all();
        };

        $titles = [];

        $tryoutRefs = $refs('tryout');
        if ($tryoutRefs) {
            DB::table('tryout_segment_tests as tst')
                ->leftJoin('tests', 'tests.uuid', '=', 'tst.test_uuid')
                ->leftJoin('tryout_segments as ts', 'ts.uuid', '=', 'tst.tryout_segment_uuid')
                ->leftJoin('tryouts', 'tryouts.uuid', '=', 'ts.tryout_uuid')
                ->whereIn('tst.uuid', $tryoutRefs)
                ->select('tst.uuid', 'tests.title as test_title', 'tests.student_title_display', 'ts.title as segment_title', 'tryouts.title as tryout_title')
                ->get()
                ->each(function ($row) use (&$titles) {
                    $test = $row->student_title_display ?: $row->test_title;
                    $titles['tryout:' . $row->uuid] = [
                        'title' => $row->tryout_title ?: $test,
                        'subtitle' => collect([$row->segment_title, $test])->filter()->unique()->implode(' · ') ?: null,
                    ];
                });
        }

        $quizRefs = $refs('quiz');
        if ($quizRefs) {
            DB::table('lesson_quizzes')->whereIn('uuid', $quizRefs)->select('uuid', 'title')->get()
                ->each(function ($row) use (&$titles) {
                    $titles['quiz:' . $row->uuid] = ['title' => $row->title, 'subtitle' => null];
                });
        }

        $posttestRefs = $refs('posttest');
        if ($posttestRefs) {
            DB::table('pretest_posttests as pp')
                ->leftJoin('tests', 'tests.uuid', '=', 'pp.test_uuid')
                ->whereIn('pp.uuid', $posttestRefs)
                ->select('pp.uuid', 'tests.title', 'tests.student_title_display')
                ->get()
                ->each(function ($row) use (&$titles) {
                    $titles['posttest:' . $row->uuid] = ['title' => $row->student_title_display ?: $row->title, 'subtitle' => null];
                });
        }

        return $titles;
    }
}
