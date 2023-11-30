<?php
namespace App\Traits;

use App\Models\Subject;
use Illuminate\Http\JsonResponse;

trait SubjectValidationTrait
{
    public function validateSubject($subjectUuid): ?JsonResponse
    {
        $checkSubject = Subject::where('uuid', $subjectUuid)->first();

        if (!$checkSubject) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'subject_uuid' => ['Subject not found'],
                ],
            ], 422);
        }

        return null;
    }
}
