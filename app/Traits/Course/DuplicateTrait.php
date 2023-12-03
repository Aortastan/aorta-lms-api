<?php
namespace App\Traits\Course;
use Ramsey\Uuid\Uuid;
use File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Course;
use App\Models\CourseTag;
use App\Models\PretestPosttest;
use App\Models\CourseLesson;
use App\Models\LessonQuiz;
use App\Models\Assignment;
use App\Models\LessonLecture;

trait DuplicateTrait
{
    public function duplicateCourse($request, $uuid){
        try{
            $course = Course::where(['uuid' => $uuid])
            ->with([
                'lessons',
                'lessons.assignments',
                'lessons.lectures',
                'lessons.quizzes',
                'pretestPosttests',
                'tags'
                ])
            ->first();

            $new_course = $this->storeCourse($request, $course);
            $this->duplicateCourseTags($new_course->uuid, $course->tags);
            $this->duplicatePretestPosttests($new_course->uuid, $course->pretestPosttests);
            $this->duplicateLessons($new_course->uuid, $course->lessons);

            return response()->json([
                'message' => 'Success duplicate test',
            ]);
        }
        catch(\Exception $e){
            return response()->json([
                'message' => $e,
            ], 404);
        }
    }

    public function storeCourse($request, $test){
        $image = "";
        if($test->image){
            $sourcePath = 'storage/' . $test->image; // Sesuaikan dengan path yang sesuai
            $originalFileName = basename($test->image);
            $destinationFolder = 'storage/courses/';
            // Pastikan file ada sebelum mencoba menyalin
            if (Storage::exists($sourcePath)) {
                // Dapatkan informasi ekstensi file asli
                $pathInfo = pathinfo($originalFileName);
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                // Buat nama file baru secara otomatis dengan UUID
                $newFileName = Str::uuid()->toString();
                $newFileNameWithExtension = $newFileName . $extension;
                $image = 'courses/' . $newFileNameWithExtension;

                // Salin file
                Storage::copy($sourcePath, $destinationFolder . $originalFileName);

                // Ganti nama file di path tujuan dengan nama baru + ekstensi
                Storage::move($destinationFolder . $originalFileName, $destinationFolder . $newFileNameWithExtension);
            }
        }
        $video = "";
        if($test->video){
            $sourcePath = 'storage/' . $test->video; // Sesuaikan dengan path yang sesuai
            $originalFileName = basename($test->video);
            $destinationFolder = 'storage/courses/';
            // Pastikan file ada sebelum mencoba menyalin
            if (Storage::exists($sourcePath)) {
                // Dapatkan informasi ekstensi file asli
                $pathInfo = pathinfo($originalFileName);
                $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                // Buat nama file baru secara otomatis dengan UUID
                $newFileName = Str::uuid()->toString();
                $newFileNameWithExtension = $newFileName . $extension;
                $video = 'courses/' . $newFileNameWithExtension;

                // Salin file
                Storage::copy($sourcePath, $destinationFolder . $originalFileName);

                // Ganti nama file di path tujuan dengan nama baru + ekstensi
                Storage::move($destinationFolder . $originalFileName, $destinationFolder . $newFileNameWithExtension);
            }
        }
        $cleaned_data = [
            "title" => $request->title,
            'description' => $test->description,
            'image' => $image,
            'video' => $video,
            'number_of_meeting' => $test->number_of_meeting,
            'is_have_pretest_posttest' => $test->is_have_pretest_posttest,
            'instructor_uuid' => $test->instructor_uuid,
            'status' => 'Draft'
        ];

        $course = Course::create($cleaned_data);

        return $course;
    }

    public function duplicateCourseTags($course_uuid, $tags){
        $new_tags = [];
        foreach ($tags as $index => $tag) {
            $new_tags[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'course_uuid' => $course_uuid,
                'tag_uuid' => $tag->tag_uuid,
            ];

            if(count($new_tags) > 0){
                CourseTag::insert($new_tags);
            }
        }
    }

    public function duplicatePretestPosttests($course_uuid, $pretest_posttests){
        $new_pretest_posttests = [];
        foreach ($pretest_posttests as $index => $test) {
            $new_pretest_posttests[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'course_uuid' => $course_uuid,
                'test_uuid' => $test->test_uuid,
                'duration' => $test->duration,
                'max_attempt' => $test->max_attempt,
            ];

            if(count($new_pretest_posttests) > 0){
                PretestPosttest::insert($new_pretest_posttests);
            }
        }
    }

    public function duplicateLessons($course_uuid, $lessons){
        $lessons = [];
        $quizzes = [];
        $assignments = [];
        $lectures = [];

        foreach ($lessons as $index => $lesson) {
            $uuid_lesson = Uuid::uuid4()->toString();
            $lessons[] = [
                "uuid" => $uuid_lesson,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'is_have_quiz' => $lesson->is_have_quiz,
                'is_have_assignment' => $lesson->is_have_assignment,
            ];

            foreach ($lesson->quizzes as $index1 => $quiz) {
                $quizzes[] =[
                    'uuid' => Uuid::uuid4()->toString(),
                    'test_uuid'=> $quiz->test_uuid,
                    'lesson_uuid' => $uuid_lesson,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'duration' => $quiz->duration,
                    'max_attempt' => $quiz->max_attempt,
                    'status' => $quiz->status,
                ];
            }

            foreach ($lesson->assignments as $index1 => $assignment) {
                $assignments[] =[
                    'uuid' => Uuid::uuid4()->toString(),
                    'lesson_uuid' => $uuid_lesson,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'point' => $assignment->point,
                    'grading_type' => $assignment->grading_type,
                ];
            }

            foreach ($lesson->lectures as $index1 => $lecture) {
                $path = "";
                if($lecture->file_path){
                    $sourcePath = 'storage/' . $lecture->file_path; // Sesuaikan dengan path yang sesuai
                    $originalFileName = basename($lecture->file_path);
                    $destinationFolder = 'storage/lectures/';
                    // Pastikan file ada sebelum mencoba menyalin
                    if (Storage::exists($sourcePath)) {
                        // Dapatkan informasi ekstensi file asli
                        $pathInfo = pathinfo($originalFileName);
                        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

                        // Buat nama file baru secara otomatis dengan UUID
                        $newFileName = Str::uuid()->toString();
                        $newFileNameWithExtension = $newFileName . $extension;
                        $path = 'lectures/' . $newFileNameWithExtension;

                        // Salin file
                        Storage::copy($sourcePath, $destinationFolder . $originalFileName);

                        // Ganti nama file di path tujuan dengan nama baru + ekstensi
                        Storage::move($destinationFolder . $originalFileName, $destinationFolder . $newFileNameWithExtension);
                    }
                }
                $lectures[] =[
                    'uuid' => Uuid::uuid4()->toString(),
                    'lesson_uuid' => $uuid_lesson,
                    'title' => $lecture->title,
                    'body' => $lecture->body,
                    'file_path' => $path,
                    'url_path' => $lecture->url_path,
                    'file_size' => $lecture->file_size,
                    'file_duration' => $lecture->file_duration,
                    'type' => $lecture->type,
                ];
            }
        }

        if(count($lessons) > 0){
            CourseLesson::insert($lessons);
        }

        if(count($quizzes) > 0){
            LessonQuiz::insert($quizzes);
        }

        if(count($assignments) > 0){
            Assignment::insert($assignments);
        }

        if(count($lectures) > 0){
            LessonLecture::insert($lectures);
        }
    }
}
