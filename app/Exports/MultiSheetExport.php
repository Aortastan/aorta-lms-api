<?php

namespace App\Exports;

use App\Models\Tryout;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSheetExport implements WithMultipleSheets
{
    private $tryout_uuid;
    private $user_uuid;
    public function __construct($tryout_uuid, $user_uuid = null)
    {
        $this->tryout_uuid = $tryout_uuid;
        $this->user_uuid = $user_uuid;
    }
    public function sheets(): array
    {
        $sheets = [];
        if ($this->user_uuid == null) {
            $studentTryouts = Tryout::where('uuid', $this->tryout_uuid)->with('tryoutSegments.tryoutSegmentTests.studentTryouts')->get();
        } else {
            $studentTryouts = Tryout::where('uuid', $this->tryout_uuid)->with(['tryoutSegments.tryoutSegmentTests.studentTryouts' => fn($query) => $query->where('user_uuid', $this->user_uuid)])->get();
        }
        foreach ($studentTryouts as $tryout) {
            foreach ($tryout->tryoutSegments as $tryoutSegment) {
                foreach ($tryoutSegment->tryoutSegmentTests as $tryoutSegmentTest) {
                    foreach ($tryoutSegmentTest->studentTryouts as $studentTryout) {
                        $user = User::where('uuid', $studentTryout->user_uuid)->first();
                        $studentName = $user ? $user->username : 'Unknown';
                        $sheets[$studentName] = new StudentTryoutExport($studentTryout->uuid, $studentTryout->user_uuid, null, $this->tryout_uuid);
                    }
                }
            }
        }
        return $sheets;
    }
}
