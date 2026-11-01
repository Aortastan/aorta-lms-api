<?php

namespace App\Services;

use App\Models\IrtPoint;
use App\Models\SessionTest;
use App\Models\StudentQuestionGrade;
use App\Models\StudentTryout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TryoutResultReset
{
    const REASON_MANUAL = 'manual';
    const REASON_QUESTIONS_CHANGED = 'questions_changed';
    const REASON_TEST_REPLACED = 'test_replaced';
    const REASON_TEST_REMOVED = 'test_removed';
    const REASON_SETTINGS_CHANGED = 'settings_changed';
    const SCORING_SETTINGS = ['max_point', 'passing_score', 'duration', 'duration_per_question', 'duration_type'];

    public static function scoringSettingsChanged($segmentTest, array $incoming): bool
    {
        $normalize = function ($value) {
            return $value === null ? '' : trim((string) $value);
        };
        foreach (self::SCORING_SETTINGS as $field) {
            if ($normalize($segmentTest->{$field}) !== $normalize($incoming[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public static function reset(string $segmentTestUuid, string $reason, ?string $resetBy = null): array
    {
        $sessionUuids = [];

        $result = DB::transaction(function () use ($segmentTestUuid, $reason, $resetBy, &$sessionUuids) {
            $attempts = StudentTryout::where('package_test_uuid', $segmentTestUuid)->get();
            $attemptUuids = $attempts->pluck('uuid')->all();

            $gradesByAttempt = $attemptUuids
                ? StudentQuestionGrade::whereIn('student_quiz_uuid', $attemptUuids)
                    ->where('student_quiz_type', 'tryout')
                    ->get()
                    ->groupBy('student_quiz_uuid')
                : collect();

            $now = now();
            foreach ($attempts->chunk(200) as $chunk) {
                DB::table('student_tryout_archives')->insert($chunk->map(function ($attempt) use ($gradesByAttempt, $reason, $resetBy, $now) {
                    $grades = $gradesByAttempt->get($attempt->uuid);

                    return [
                        'student_tryout_uuid' => $attempt->uuid,
                        'package_test_uuid' => $attempt->package_test_uuid,
                        'user_uuid' => $attempt->user_uuid,
                        'package_uuid' => $attempt->package_uuid,
                        'attempt' => $attempt->attempt,
                        'score' => $attempt->score,
                        'payload' => json_encode($attempt->getAttributes()),
                        'grades_payload' => $grades ? json_encode($grades->map->getAttributes()->values()) : null,
                        'reason' => $reason,
                        'reset_by' => $resetBy,
                        'reset_at' => $now,
                    ];
                })->values()->all());
            }

            if ($attemptUuids) {
                StudentQuestionGrade::whereIn('student_quiz_uuid', $attemptUuids)->where('student_quiz_type', 'tryout')->delete();
            }
            StudentTryout::where('package_test_uuid', $segmentTestUuid)->delete();

            $sessionUuids = SessionTest::where(['package_test_uuid' => $segmentTestUuid, 'type_test' => 'tryout'])->pluck('uuid')->all();
            SessionTest::where(['package_test_uuid' => $segmentTestUuid, 'type_test' => 'tryout'])->delete();
            IrtPoint::where('package_test_uuid', $segmentTestUuid)->delete();

            return [
                'attempts' => count($attemptUuids),
                'students' => $attempts->pluck('user_uuid')->unique()->count(),
                'sessions' => count($sessionUuids),
            ];
        });

        foreach ($sessionUuids as $sessionUuid) {
            try {
                Redis::del("test_session:{$sessionUuid}", "test_session_last_db:{$sessionUuid}");
            } catch (\Exception $e) {
                report($e);
            }
        }

        return $result;
    }
}
