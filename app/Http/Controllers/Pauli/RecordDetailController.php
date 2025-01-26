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
            'details.*.time' => 'required|date_format:H:i:s',
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
}
