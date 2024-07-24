<?php

namespace App\Http\Controllers\Pauli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\PauliRecord;

class LeaderboardController extends Controller
{
    public function getLeaderboard($selected_time)
    {
        // Selected time minimum 30 minutes
        if ($selected_time < 30) {
            return response()->json(['error' => 'Selected time must be at least 30 minutes'], 422);
        }

        $selectedTime = $selected_time;

        $records = PauliRecord::where('selected_time', $selectedTime)
            ->with('user')
            ->get();

        $leaderboard = $records->map(function ($record) {
            $totalQuestions = $record->total_correct + $record->total_wrong;
            $percentageCorrect = $totalQuestions > 0 ? ($record->total_correct / $totalQuestions) * 100 : 0;

            return [
                'user_uuid' => $record->user->uuid,
                'user_name' => $record->user->name,
                'total_questions' => $totalQuestions,
                'correct' => $record->total_correct,
                'wrong' => $record->total_wrong,
                'percentage_correct' => $percentageCorrect
            ];
        });

        $leaderboard = $leaderboard->sort(function ($a, $b) {
            if ($a['total_questions'] === $b['total_questions']) {
                return $b['percentage_correct'] <=> $a['percentage_correct'];
            }
            return $b['total_questions'] <=> $a['total_questions'];
        })->values();

        return response()->json([
            'selected_time' => $selectedTime,
            'leaderboard' => $leaderboard
        ], 200);
    }
}
