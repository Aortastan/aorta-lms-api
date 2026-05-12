<?php

namespace App\Exports;

use App\Models\LessonAttendances;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements
    FromCollection,
    WithHeadings,
    WithMapping
{
    private $lessonLectureUuid;

    public function __construct(
        $lessonLectureUuid
    ) {
        $this->lessonLectureUuid =
            $lessonLectureUuid;
    }

    public function collection()
    {
        return LessonAttendances::query()

            ->where(
                "lesson_lecture_uuid",
                $this->lessonLectureUuid
            )
            ->with([
                "user",
                "lesson",
            ])
            ->get();
    }

    public function map(
        $item
    ): array {
        return [
            $item->lesson->title ?? "-",
            $item->user->name ?? "-",

            $item->start_attendance
                ?: "Belum Absen",

            $item->end_attendance
                ?: "Belum Absen",

            $item->note
                ?: "-",
        ];
    }

    public function headings(): array
    {
        return [
            "Materi",
            "Nama",
            "Absen Awal",
            "Absen Akhir",
            "Catatan",
        ];
    }
}