<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Presence;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class PresenceController extends Controller
{
    public function peers(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $online = Presence::onlineUsersByCategory();

        $categories = Presence::userCategories([$user->uuid])
            ->map(function ($row) use ($online, $user) {
                $peers = array_diff($online[$row->category_uuid] ?? [], [$user->uuid]);

                return [
                    'uuid' => $row->category_uuid,
                    'name' => $row->category_name,
                    'online' => count($peers),
                ];
            })
            ->sortByDesc('online')
            ->values();

        return response()->json([
            'message' => 'Success get data',
            'categories' => $categories,
        ], 200);
    }
}
