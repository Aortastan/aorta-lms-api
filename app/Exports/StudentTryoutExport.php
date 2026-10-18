<?php

namespace App\Exports;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithTitle;

class StudentTryoutExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithDrawings, WithEvents, WithTitle
{
    protected $studentTryout;
    protected $data_question;
    protected $headings = [];
    protected $imagesQuestion = [];
    protected $imagesAnswer = [];
    protected $rowPosition = [];
    protected $rowPositionAnswer = [];
    protected $name;
    protected $test;
    protected $attempt;
    protected $score;
    protected $title;

    public function __construct($studentTryout, $tryoutSegmentTest)
    {
        $this->studentTryout = $studentTryout;
        $this->data_question = is_string($studentTryout->data_question) ? json_decode($studentTryout->data_question) : $studentTryout->data_question;
        $user = $studentTryout->user;
        $this->name = $user ? $user->name : "";
        $this->test = $tryoutSegmentTest->test ?? null;
        $this->attempt = $tryoutSegmentTest->attempt ?? 1;
        $this->score = $studentTryout->score ?? 0;
        
        $rawTitle = ($this->test ? $this->test->title : "Test");
        // Title Excel sheet dibatasi maksimal 31 karakter oleh Excel
        $this->title = mb_substr(preg_replace('/[\:\/\\\?\*\[\]]/', '_', $rawTitle), 0, 31);

        $this->headings = [
            ["Nama : " . $this->name],
            ["Test : " . ($this->test ? $this->test->title : '')],
            ["Skor : " . $this->score],
            ["Percobaan : " . $this->attempt],
            ['No.', 'Soal', 'Jawaban', 'Poin']
        ];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function collection()
    {
        $numberedData = new Collection();
        $cellStartAt = count($this->headings) + 1;

        if (empty($this->data_question) || !is_iterable($this->data_question)) {
            return $numberedData;
        }

        // Kumpulkan semua question_uuid dan answer_uuid untuk di-batch fetch sekaligus (mengatasi N+1 query)
        $qUuids = [];
        $aUuids = [];
        foreach ($this->data_question as $data) {
            if (!empty($data->question_uuid)) {
                $qUuids[] = $data->question_uuid;
            }
            foreach ($data->answers ?? [] as $aData) {
                if (!empty($aData->answer_uuid)) {
                    $aUuids[] = $aData->answer_uuid;
                }
            }
        }

        $questionsMap = Question::whereIn('uuid', array_unique($qUuids))->pluck('question', 'uuid')->toArray();
        $answersMap = Answer::whereIn('uuid', array_unique($aUuids))->get()->keyBy('uuid');

        foreach ($this->data_question as $key => $data) {
            $questionText = $questionsMap[$data->question_uuid] ?? null;
            if (!$questionText) {
                continue;
            }

            $answers = [];
            foreach ($data->answers ?? [] as $answerData) {
                $get_answer = $answersMap->get($answerData->answer_uuid);
                if (!$get_answer) {
                    continue;
                }

                $answers[] = [
                    'answer_uuid' => $answerData->answer_uuid,
                    'is_correct' => $answerData->is_correct,
                    'correct_answer_explanation' => $answerData->is_correct ? $get_answer->correct_answer_explanation : null,
                    'is_selected' => $answerData->is_selected,
                    'answer' => $get_answer->answer,
                    'image' => $get_answer->image,
                    'point' => $get_answer->point,
                ];
            }

            $selected_answer = collect($answers)->firstWhere('is_selected', true);
            $sanitizedAnswer = $selected_answer ? html_entity_decode(strip_tags($selected_answer['answer'])) : "";

            if (str_contains($questionText, 'img')) {
                $this->rowPosition[] = $key + $cellStartAt;
                $this->imagesQuestion["B" . ($key + $cellStartAt)] = $questionText;
            }
            if ($selected_answer && str_contains($selected_answer['answer'], 'img')) {
                $this->rowPositionAnswer[] = $key + $cellStartAt;
                $this->imagesAnswer["C" . ($key + $cellStartAt)] = $selected_answer['answer'];
            }

            $numberedData->push([
                'No.' => $key + 1,
                'Soal' => html_entity_decode(strip_tags($questionText)),
                'Jawaban' => $sanitizedAnswer,
                'Poin' => $selected_answer['point'] ?? 0,
            ]);
        }

        return $numberedData;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return ['A:Z' => ['alignment' => ['wrapText' => true]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 200, 'C' => 15];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->imagesQuestion as $cell => $imgTag) {
            $matches = [];
            preg_match('/src="([^"]+)"/', $imgTag, $matches);
            $base64 = $matches[1] ?? null;

            if ($base64 && str_contains($base64, 'base64,')) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $tempFilePath = storage_path('app/temp_image_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cell) . '_q.png');
                file_put_contents($tempFilePath, $imageData);

                $drawing = new Drawing();
                $drawing->setName("Question Image");
                $drawing->setDescription("Question image for {$cell}");
                $drawing->setPath($tempFilePath);
                $drawing->setHeight(50);
                $drawing->setCoordinates($cell);
                $drawings[] = $drawing;
            }
        }

        foreach ($this->imagesAnswer as $cell => $imgTag) {
            $matches = [];
            preg_match('/src="([^"]+)"/', $imgTag, $matches);
            $base64 = $matches[1] ?? null;

            if ($base64 && str_contains($base64, 'base64,')) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $tempFilePath = storage_path('app/temp_image_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cell) . '_a.png');
                file_put_contents($tempFilePath, $imageData);

                $drawing = new Drawing();
                $drawing->setName("Answer Image");
                $drawing->setDescription("Answer image for {$cell}");
                $drawing->setPath($tempFilePath);
                $drawing->setHeight(50);
                $drawing->setCoordinates($cell);
                $drawings[] = $drawing;
            }
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                foreach ($this->rowPosition as $row) {
                    $text = $event->sheet->getCell('B' . $row)->getValue();
                    $lines = ceil((strlen($text) + 50) / 28);
                    $event->sheet->getDelegate()->getRowDimension($row)->setRowHeight($lines * 15);
                }
                $event->sheet->mergeCells('A1:B1');
                $event->sheet->mergeCells('A2:B2');
                $event->sheet->mergeCells('A3:B3');
                $event->sheet->mergeCells('A4:B4');
            },
        ];
    }
}
