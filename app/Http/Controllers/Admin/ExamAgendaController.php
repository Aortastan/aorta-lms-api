<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AllRole\ExamAgendaController as PublicExamAgendaController;
use App\Http\Controllers\Controller;
use App\Models\ExamAgenda;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class ExamAgendaController extends Controller
{
    public function index(): JsonResponse
    {
        $agendas = ExamAgenda::orderByDesc('is_active')->orderBy('start_at')->get();
        $packageNames = Package::whereIn('uuid', $agendas->pluck('package_uuids')->flatten()->filter()->unique()->values())
            ->pluck('name', 'uuid');

        return response()->json([
            'message' => 'Success get data',
            'max_active' => ExamAgenda::MAX_ACTIVE,
            'max_packages' => ExamAgenda::MAX_PACKAGES,
            'agendas' => $agendas->map(function ($agenda) use ($packageNames) {
                return $this->present($agenda, $packageNames);
            })->values(),
        ], 200);
    }

    public function show($uuid): JsonResponse
    {
        $agenda = ExamAgenda::find($uuid);
        if (!$agenda) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        $packageNames = Package::whereIn('uuid', $agenda->package_uuids ?: [])->pluck('name', 'uuid');

        return response()->json(['message' => 'Success get data', 'agenda' => $this->present($agenda, $packageNames)], 200);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, new ExamAgenda());
    }

    public function update(Request $request, $uuid): JsonResponse
    {
        $agenda = ExamAgenda::find($uuid);
        if (!$agenda) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        return $this->save($request, $agenda);
    }

    public function delete($uuid): JsonResponse
    {
        $agenda = ExamAgenda::find($uuid);
        if (!$agenda) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        $agenda->delete();
        Cache::forget(PublicExamAgendaController::CACHE_KEY);

        return response()->json(['message' => 'Agenda berhasil dihapus'], 200);
    }

    private function save(Request $request, ExamAgenda $agenda): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stage_label' => 'required|string|max:50',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'visibility' => 'required|in:all,packages',
            'package_uuids' => 'required_if:visibility,packages|array|max:' . ExamAgenda::MAX_PACKAGES,
            'package_uuids.*' => 'string|distinct|exists:packages,uuid',
            'is_active' => 'boolean',
        ], [
            'package_uuids.required_if' => 'Pilih minimal 1 paket',
            'package_uuids.max' => 'Maksimal ' . ExamAgenda::MAX_PACKAGES . ' paket',
            'end_at.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $timezone = config('app.timezone');
        $startAt = Carbon::parse($request->start_at)->setTimezone($timezone);
        $endAt = $request->end_at ? Carbon::parse($request->end_at)->setTimezone($timezone) : null;
        $isActive = $request->has('is_active') ? (bool) $request->is_active : ($agenda->exists ? $agenda->is_active : true);
        $stillRunning = ($endAt ?: $startAt->copy()->endOfDay())->isFuture();

        if ($isActive && $stillRunning) {
            $activeOthers = ExamAgenda::where('is_active', true)->notFinished()
                ->when($agenda->exists, function ($q) use ($agenda) {
                    $q->where('uuid', '!=', $agenda->uuid);
                })
                ->count();
            if ($activeOthers >= ExamAgenda::MAX_ACTIVE) {
                return response()->json([
                    'message' => 'Maksimal ' . ExamAgenda::MAX_ACTIVE . ' agenda aktif. Nonaktifkan salah satu agenda terlebih dahulu.',
                ], 422);
            }
        }

        $packages = $request->visibility === 'packages' ? array_values(array_unique($request->package_uuids)) : null;

        $agenda->fill([
            'stage_label' => trim($request->stage_label),
            'title' => trim($request->title),
            'description' => $request->description ? trim($request->description) : null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'visibility' => $request->visibility,
            'package_uuids' => $packages,
            'is_active' => $isActive,
        ]);
        $created = !$agenda->exists;
        $agenda->save();
        Cache::forget(PublicExamAgendaController::CACHE_KEY);

        $packageNames = Package::whereIn('uuid', $packages ?: [])->pluck('name', 'uuid');

        return response()->json([
            'message' => $created ? 'Agenda berhasil ditambahkan' : 'Agenda berhasil diperbarui',
            'agenda' => $this->present($agenda->fresh(), $packageNames),
        ], 200);
    }

    private function present(ExamAgenda $agenda, $packageNames): array
    {
        $packages = collect($agenda->package_uuids ?: [])->map(function ($uuid) use ($packageNames) {
            return ['uuid' => $uuid, 'name' => $packageNames[$uuid] ?? 'Paket terhapus'];
        })->values();

        return $agenda->toPublicArray() + [
            'visibility' => $agenda->visibility,
            'packages' => $packages,
            'is_active' => $agenda->is_active,
            'is_finished' => $agenda->finishesAt()->isPast(),
        ];
    }
}
