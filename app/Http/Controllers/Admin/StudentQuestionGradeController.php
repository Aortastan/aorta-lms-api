<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\StudentQuestionGrade;
use App\Models\StudentQuiz;
use App\Models\StudentPretestPosttest;
use App\Models\StudentTryout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentQuestionGradeController extends Controller
{
    /**
     * List semua essay (pending + scored) untuk satu student attempt.
     * GET /api/v1/admin/student-grades/{student_quiz_uuid}
     */
    public function index($student_quiz_uuid)
    {
        $grades = StudentQuestionGrade::where('student_quiz_uuid', $student_quiz_uuid)
            ->with(['question', 'student:uuid,name,email', 'scorer:uuid,name'])
            ->orderBy('created_at')
            ->get();

        // Hitung total score yang sudah diberikan + max possible score
        $totalAwarded = 0;
        $totalMaxScore = 0;
        $allScored = true;

        foreach ($grades as $g) {
            $maxPoint = $g->question->point ?? 0;
            $totalMaxScore += $maxPoint;
            if ($g->status === 'scored') {
                $totalAwarded += (float) $g->score_awarded;
            } else {
                $allScored = false;
            }
        }

        return response()->json([
            'message' => 'Success',
            'grades' => $grades,
            'summary' => [
                'total_count' => $grades->count(),
                'pending_count' => $grades->where('status', 'pending')->count(),
                'scored_count' => $grades->where('status', 'scored')->count(),
                'total_awarded' => $totalAwarded,
                'total_max_score' => $totalMaxScore,
                'all_scored' => $allScored,
            ],
        ], 200);
    }

    /**
     * Submit score + feedback untuk satu essay grade.
     * POST /api/v1/admin/student-grades/{uuid}
     * Body: { score_awarded, feedback }
     */
    public function score(Request $request, $uuid)
    {
        $grade = StudentQuestionGrade::where('uuid', $uuid)->first();
        if (!$grade) {
            return response()->json([
                'message' => 'Grade record not found',
            ], 404);
        }

        $question = Question::where('uuid', $grade->question_uuid)->first();
        $maxPoint = $question->point ?? 0;

        $validator = Validator::make($request->all(), [
            'score_awarded' => 'required|numeric|min:0|max:' . ($maxPoint > 0 ? $maxPoint : 999999),
            'feedback' => 'nullable|string',
        ], [
            'score_awarded.max' => "Score tidak boleh melebihi poin maksimum soal ({$maxPoint})",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $scorer = JWTAuth::parseToken()->authenticate();

        // Hitung delta supaya recalc attempt->score akurat saat re-grading
        $previousAwarded = $grade->status === 'scored' ? (float) $grade->score_awarded : 0;
        $delta = (float) $request->score_awarded - $previousAwarded;

        $grade->update([
            'score_awarded' => $request->score_awarded,
            'feedback' => $request->feedback,
            'scored_by_uuid' => $scorer->uuid,
            'scored_at' => now(),
            'status' => 'scored',
        ]);

        // Tambahkan delta ke total attempt score (incremental update)
        $this->applyScoreDelta($grade, $delta);

        return response()->json([
            'message' => 'Score saved',
            'grade' => $grade->fresh(['question', 'scorer:uuid,name']),
        ], 200);
    }

    /**
     * Batch: submit score untuk beberapa grade sekaligus.
     * POST /api/v1/admin/student-grades/batch
     * Body: { grades: [ {uuid, score_awarded, feedback}, ... ] }
     */
    public function batchScore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grades' => 'required|array|min:1',
            'grades.*.uuid' => 'required|string',
            'grades.*.score_awarded' => 'required|numeric|min:0',
            'grades.*.feedback' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $scorer = JWTAuth::parseToken()->authenticate();

        foreach ($request->grades as $item) {
            $grade = StudentQuestionGrade::where('uuid', $item['uuid'])->first();
            if (!$grade) continue;

            $question = Question::where('uuid', $grade->question_uuid)->first();
            $maxPoint = $question->point ?? 0;
            $score = (float) $item['score_awarded'];
            if ($maxPoint > 0 && $score > $maxPoint) {
                $score = $maxPoint;
            }

            $previousAwarded = $grade->status === 'scored' ? (float) $grade->score_awarded : 0;
            $delta = $score - $previousAwarded;

            $grade->update([
                'score_awarded' => $score,
                'feedback' => $item['feedback'] ?? null,
                'scored_by_uuid' => $scorer->uuid,
                'scored_at' => now(),
                'status' => 'scored',
            ]);

            $this->applyScoreDelta($grade, $delta);
        }

        return response()->json([
            'message' => 'Batch scores saved',
        ], 200);
    }

    /**
     * Increment / decrement attempt score by delta dari grading essay.
     * Setelah pertama submit, attempt->score = classical only. Saat instructor
     * grade essay, tambah delta = (new_award - prev_award). Re-grade juga aman
     * karena delta mengompensasi nilai sebelumnya.
     */
    private function applyScoreDelta(StudentQuestionGrade $grade, float $delta)
    {
        if (abs($delta) < 0.001) return;

        $modelMap = [
            'quiz' => StudentQuiz::class,
            'pretest_posttest' => StudentPretestPosttest::class,
            'tryout' => StudentTryout::class,
        ];
        $modelClass = $modelMap[$grade->student_quiz_type] ?? null;
        if (!$modelClass) return;

        $attempt = $modelClass::where('uuid', $grade->student_quiz_uuid)->first();
        if (!$attempt) return;

        $attempt->update(['score' => (float) $attempt->score + $delta]);
    }
}
