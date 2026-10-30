<?php
namespace App\Traits\Course;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait DuplicateTrait
{
    public function duplicateCourse($request, $uuid)
    {
        $course = Course::where(['uuid' => $uuid])
            ->with(['lessons.assignments', 'lessons.lectures', 'lessons.quizzes', 'pretestPosttests', 'tags'])
            ->first();

        try {
            $newCourse = DB::transaction(function () use ($request, $course) {
                $copy = $course->replicate(['id']);
                $copy->title = $request->title;
                $copy->status = 'Draft';
                $copy->image = $this->copyPublicFile($course->image, 'courses');
                $copy->video = $this->copyPublicFile($course->video, 'courses');
                $copy->save();

                foreach ($course->tags as $tag) {
                    $this->cloneRow($tag, ['course_uuid' => $copy->uuid]);
                }

                foreach ($course->pretestPosttests as $pretestPosttest) {
                    $this->cloneRow($pretestPosttest, ['course_uuid' => $copy->uuid]);
                }

                foreach ($course->lessons as $lesson) {
                    $newLesson = $this->cloneRow($lesson, ['course_uuid' => $copy->uuid]);

                    foreach ($lesson->quizzes as $quiz) {
                        $this->cloneRow($quiz, ['lesson_uuid' => $newLesson->uuid]);
                    }

                    foreach ($lesson->assignments as $assignment) {
                        $this->cloneRow($assignment, ['lesson_uuid' => $newLesson->uuid]);
                    }

                    $this->duplicateLectures($lesson->lectures, $newLesson->uuid);
                }

                return $copy;
            });
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal menduplikat course',
            ], 500);
        }

        return response()->json([
            'message' => 'Course berhasil diduplikat',
            'course_uuid' => $newCourse->uuid,
        ], 200);
    }

    private function duplicateLectures($lectures, $lessonUuid)
    {
        $uuidMap = [];
        $pending = $lectures->all();

        while (count($pending) > 0) {
            $remaining = [];
            foreach ($pending as $lecture) {
                $parent = $lecture->parent_lecture_uuid;
                if ($parent && !isset($uuidMap[$parent])) {
                    $remaining[] = $lecture;
                    continue;
                }

                $newLecture = $this->cloneRow($lecture, [
                    'lesson_uuid' => $lessonUuid,
                    'parent_lecture_uuid' => $parent ? $uuidMap[$parent] : null,
                    'file_path' => $this->copyPublicFile($lecture->file_path, 'lectures'),
                ]);
                $uuidMap[$lecture->uuid] = $newLecture->uuid;
            }

            if (count($remaining) === count($pending)) {
                foreach ($remaining as $lecture) {
                    $lecture->parent_lecture_uuid = null;
                }
            }
            $pending = $remaining;
        }
    }

    private function cloneRow(Model $model, array $overrides): Model
    {
        $copy = $model->replicate(['id']);
        foreach ($overrides as $key => $value) {
            $copy->{$key} = $value;
        }
        $copy->save();

        return $copy;
    }

    private function copyPublicFile($path, $folder)
    {
        if (!$path || Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $folder . '/' . Str::uuid()->toString() . ($extension ? '.' . $extension : '');
        $disk->copy($path, $target);

        return $target;
    }
}
