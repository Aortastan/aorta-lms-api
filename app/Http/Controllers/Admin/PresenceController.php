<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserPresence;
use App\Services\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PresenceController extends Controller
{
    const USER_LIMIT = 1000;

    public function index(): JsonResponse
    {
        $presences = UserPresence::online()
            ->join('users', 'users.uuid', '=', 'user_presences.user_uuid')
            ->where('users.role', 'student')
            ->orderByDesc('user_presences.activity_started_at')
            ->select('user_presences.*', 'users.name', 'users.email', 'users.username')
            ->get();

        $uuids = $presences->pluck('user_uuid')->all();
        $categoryRows = Presence::userCategories($uuids);
        $categoriesByUser = $categoryRows->groupBy('user_uuid');
        $tests = Presence::describeTests($presences);
        $packageNames = DB::table('packages')
            ->whereIn('uuid', $presences->pluck('package_uuid')->filter()->unique()->values())
            ->pluck('name', 'uuid');

        $users = $presences->map(function ($p) use ($categoriesByUser, $tests, $packageNames) {
            $test = $tests[$p->activity . ':' . $p->test_ref_uuid] ?? null;

            return [
                'user_uuid' => $p->user_uuid,
                'name' => $p->name ?: $p->username,
                'email' => $p->email,
                'activity' => $p->activity,
                'activity_label' => Presence::ACTIVITY_LABELS[$p->activity] ?? $p->activity,
                'is_working' => $p->activity !== 'browsing',
                'test_ref_uuid' => $p->test_ref_uuid,
                'test_title' => $test['title'] ?? null,
                'test_subtitle' => $test['subtitle'] ?? null,
                'package_name' => $p->package_uuid ? ($packageNames[$p->package_uuid] ?? null) : null,
                'path' => $p->path,
                'categories' => collect($categoriesByUser[$p->user_uuid] ?? [])->map(function ($c) {
                    return ['uuid' => $c->category_uuid, 'name' => $c->category_name];
                })->values(),
                'activity_started_at' => optional($p->activity_started_at)->toIso8601String(),
                'last_seen_at' => optional($p->last_seen_at)->toIso8601String(),
            ];
        });

        $working = $users->where('is_working', true);

        $byCategory = $categoryRows->groupBy('category_uuid')->map(function ($rows) use ($users) {
            $members = $users->whereIn('user_uuid', $rows->pluck('user_uuid')->all());
            $workingCount = $members->where('is_working', true)->count();

            return [
                'uuid' => $rows->first()->category_uuid,
                'name' => $rows->first()->category_name,
                'online' => $members->count(),
                'working' => $workingCount,
                'browsing' => $members->count() - $workingCount,
            ];
        })->sortByDesc('online')->values();

        $byTest = $working->groupBy(function ($u) {
            return $u['activity'] . ':' . ($u['test_ref_uuid'] ?: '-');
        })->map(function ($group) {
            $first = $group->first();

            return [
                'activity' => $first['activity'],
                'activity_label' => $first['activity_label'],
                'test_ref_uuid' => $first['test_ref_uuid'],
                'title' => $first['test_title'] ?: $first['activity_label'],
                'subtitle' => $first['test_subtitle'],
                'packages' => $group->pluck('package_name')->filter()->unique()->values(),
                'count' => $group->count(),
            ];
        })->sortByDesc('count')->values();

        $byActivity = [];
        foreach (UserPresence::ACTIVITIES as $activity) {
            $byActivity[$activity] = $users->where('activity', $activity)->count();
        }

        return response()->json([
            'message' => 'Success get data',
            'generated_at' => now()->toIso8601String(),
            'online_window_seconds' => UserPresence::ONLINE_SECONDS,
            'summary' => [
                'total' => $users->count(),
                'working' => $working->count(),
                'browsing' => $users->count() - $working->count(),
                'without_package' => $users->filter(function ($u) {
                    return count($u['categories']) === 0;
                })->count(),
            ],
            'by_activity' => $byActivity,
            'by_category' => $byCategory,
            'by_test' => $byTest,
            'users' => $users->take(self::USER_LIMIT)->values(),
        ], 200);
    }
}
