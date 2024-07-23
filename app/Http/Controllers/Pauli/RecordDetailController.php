<?php

namespace App\Http\Controllers\Pauli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Models\PauliRecord;
use App\Models\PauliRecordDetail;

class RecordDetailController extends Controller
{
    public function postRecordDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'details' => 'required|array',
            'details.*.pauli_record_id' => 'required|exists:pauli_records,id',
            'details.*.correct' => 'required|boolean',
            'details.*.wrong' => 'required|boolean',
            'details.*.time' => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $details = $request->input('details');

        foreach ($details as $detail) {
            PauliRecordDetail::create($detail);
        }

        return response()->json([
            'message' => 'Record details have been saved successfully.',
        ], 200);
    }

    public function getDataByInterval(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'interval' => 'required|integer|min:1',
            'user_uuid' => 'required|string|exists:users,uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $interval = $request->input('interval');
        $userUuid = $request->input('user_uuid');

        $pauliRecords = PauliRecord::where('user_uuid', $userUuid)
            ->with('pauliRecordDetails')
            ->get()
            ->map(function ($record) use ($interval) {
                $startTime = new \DateTime($record->time_start);
                $endTime = (clone $startTime)->modify("+{$interval} seconds");

                $detailsInInterval = $record->pauliRecordDetails
                    ->filter(function ($detail) use ($startTime, $endTime) {
                        $detailTime = new \DateTime($detail->time);
                        return $detailTime >= $startTime && $detailTime <= $endTime;
                    });

                return [
                    'record_id' => $record->id,
                    'total_correct' => $detailsInInterval->where('correct', true)->count(),
                    'total_wrong' => $detailsInInterval->where('wrong', true)->count(),
                ];
            });

        $totalCorrect = $pauliRecords->sum('total_correct');
        $totalWrong = $pauliRecords->sum('total_wrong');

        return response()->json([
            'user_uuid' => $userUuid,
            'total_correct' => $totalCorrect,
            'total_wrong' => $totalWrong,
            'details' => $pauliRecords,
        ], 200);
    }
}
