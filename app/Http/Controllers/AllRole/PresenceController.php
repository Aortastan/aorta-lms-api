<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use App\Models\UserPresence;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class PresenceController extends Controller
{
    const MIN_WRITE_SECONDS = 20;
    const TEST_ACTIVITIES = ['tryout', 'quiz', 'posttest'];

    public function heartbeat(Request $request): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (!$user || $user->role !== 'student') {
            return response()->json(['message' => 'Ignored'], 200);
        }

        $validator = Validator::make($request->all(), [
            'activity' => 'nullable|in:' . implode(',', UserPresence::ACTIVITIES),
            'ref_uuid' => 'nullable|string|max:64',
            'package_uuid' => 'nullable|string|max:64',
            'path' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $activity = $request->activity ?: 'browsing';
        $isTest = in_array($activity, self::TEST_ACTIVITIES, true);
        $ref = $isTest && Str::isUuid((string) $request->ref_uuid) ? $request->ref_uuid : null;
        $package = $isTest && Str::isUuid((string) $request->package_uuid) ? $request->package_uuid : null;
        $path = $request->path ? Str::limit($request->path, 188) : null;

        $presence = UserPresence::firstOrNew(['user_uuid' => $user->uuid]);
        $lastSeen = $presence->last_seen_at;
        $sameActivity = $presence->exists && $presence->activity === $activity && $presence->test_ref_uuid === $ref;
        $wasOnline = $lastSeen && $lastSeen->gte(UserPresence::onlineSince());

        if ($sameActivity && $presence->path === $path && $lastSeen && $lastSeen->gt(now()->subSeconds(self::MIN_WRITE_SECONDS))) {
            return response()->json(['message' => 'ok'], 200);
        }

        $presence->fill([
            'activity' => $activity,
            'test_ref_uuid' => $ref,
            'package_uuid' => $package,
            'path' => $path,
            'last_seen_at' => now(),
        ]);
        if (!$sameActivity || !$wasOnline || !$presence->activity_started_at) {
            $presence->activity_started_at = now();
        }

        try {
            $presence->save();
        } catch (QueryException $e) {
        }

        return response()->json(['message' => 'ok'], 200);
    }

    public function offline(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user) {
            UserPresence::where('user_uuid', $user->uuid)->delete();
        }

        return response()->json(['message' => 'ok'], 200);
    }
}
