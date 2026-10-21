<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use App\Models\ExamAgenda;
use App\Services\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExamAgendaController extends Controller
{
    const CACHE_KEY = 'exam_agendas_active';
    const CACHE_SECONDS = 300;

    public function index(): JsonResponse
    {
        $agendas = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return ExamAgenda::where('is_active', true)->notFinished()->orderBy('start_at')->get();
        })->filter(function ($agenda) {
            return $agenda->finishesAt()->isFuture();
        });

        $user = $this->currentUser();
        $restricted = $agendas->where('visibility', 'packages');

        if ($restricted->isNotEmpty() && !($user && $user->role === 'admin')) {
            $candidates = $restricted->pluck('package_uuids')->flatten()->filter()->unique()->values()->all();
            $owned = $user && $candidates ? Presence::ownedPackageUuids($user->uuid, $candidates) : [];

            $agendas = $agendas->filter(function ($agenda) use ($owned) {
                return $agenda->visibility !== 'packages'
                    || count(array_intersect($agenda->package_uuids ?: [], $owned)) > 0;
            });
        }

        return response()->json([
            'message' => 'Success get data',
            'server_time' => now()->toIso8601String(),
            'agendas' => $agendas->take(ExamAgenda::MAX_ACTIVE)->map(function ($agenda) {
                return $agenda->toPublicArray();
            })->values(),
        ], 200);
    }

    private function currentUser()
    {
        if (!request()->bearerToken()) {
            return null;
        }
        try {
            return JWTAuth::parseToken()->authenticate() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
