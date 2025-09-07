<?php

namespace App\Exports;

use App\Models\Answer;
use App\Models\Question;
use App\Models\StudentTryout;
use App\Models\Tryout;
use App\Models\TryoutSegmentTest;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithTitle;


class StudentTryoutExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithDrawings, WithEvents, WithTitle
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $atempt_uuid;
    protected $user_uuid;
    protected $question_uuid;
    protected $tryout_uuid;
    protected $imagesQuestion = [];
    protected $imagesAnswer = [];
    protected $rowPosition = [];
    protected $rowPositionAnswer = [];
    protected $title = " - Tryout";
    protected $name;
    protected $score;
    protected $test;
    protected $attempt;
    protected $headings = [];


    public function __construct($atempt_uuid, $user_uuid, $question_uuid = null, $tryout_uuid)
    {
        $this->atempt_uuid = $atempt_uuid;
        $this->user_uuid = $user_uuid;
        $this->question_uuid = $question_uuid;
        $this->tryout_uuid = $tryout_uuid;
        $tryout = StudentTryout::select('uuid', 'score', 'package_test_uuid', 'data_question')
            ->where([
                'user_uuid' => $this->user_uuid,
                'uuid' => $this->atempt_uuid,
            ])->first();

        if ($tryout == null) {
            throw new \Exception("Tes tidak ditemukan");
        }

        $getTest = TryoutSegmentTest::select(
            'uuid',
            'test_uuid',
            'attempt',
            'duration'
        )
            ->where(['uuid' => $tryout->package_test_uuid])
            ->with(['test'])
            ->first();

        if (!$getTest) {
            return response()->json([
                'message' => "Tes tidak ditemukan",
            ], 404);
        }
        $this->test = $getTest->test;
        $this->score = $tryout->score;
        $this->attempt = $getTest->attempt;
        $name = User::where('uuid', $this->user_uuid)->first()->name;
        $this->name = $name;
        $this->title = $name;
        $this->title .= " - " . $getTest->test->title . " ";
        $this->title .= "(Percobaan " . $getTest->attempt . ")";
        $this->headings = [
            ["Nama : " . $this->name],
            ["Test : " . $this->test ? $this->test->title : ""],
            ["Skor : " .  (string)$this->score],
            ["Percobaan : " . $this->attempt],
            [
                'No.',
                'Soal',
                'Jawaban',
            ]
        ];
    }

    public function collection()
    {
        $tryout = StudentTryout::select('uuid', 'score', 'package_test_uuid', 'data_question')
            ->where([
                'user_uuid' => $this->user_uuid,
                'uuid' => $this->atempt_uuid,
            ])->first();

        if ($tryout == null) {
            throw new \Exception("Tes tidak ditemukan");
        }

        $getTest = TryoutSegmentTest::select(
            'uuid',
            'test_uuid',
            'attempt',
            'duration'
        )
            ->where(['uuid' => $tryout->package_test_uuid])
            ->with(['test'])
            ->first();

        if (!$getTest) {
            return response()->json([
                'message' => "Tes tidak ditemukan",
            ], 404);
        }


        $data_question = json_decode($tryout->data_question);

        $questions = [];
        foreach ($data_question as $index => $data) {
            $get_question = Question::where([
                'uuid' => $data->question_uuid,
            ])->first();

            $answers = [];
            foreach ($data->answers as $index => $answer) {
                $get_answer = Answer::where([
                    'uuid' => $answer->answer_uuid,
                ])->first();

                if ($answer->is_correct) {
                    $answers[] = [
                        'answer_uuid' => $answer->answer_uuid,
                        'is_correct' => $answer->is_correct,
                        'correct_answer_explanation' => $get_answer->correct_answer_explanation,
                        'is_selected' => $answer->is_selected,
                        'answer' => $get_answer->answer,
                        'image' => $get_answer->image,
                    ];
                } else {
                    $answers[] = [
                        'answer_uuid' => $answer->answer_uuid,
                        'is_correct' => $answer->is_correct,
                        'is_selected' => $answer->is_selected,
                        'answer' => $get_answer->answer,
                        'image' => $get_answer->image,
                    ];
                }
            }

            $questions[] = [
                'question_uuid' => $get_question->uuid,
                'question_type' => $get_question->question_type,
                'question' => $get_question->question,
                'file_path' => $get_question->file_path,
                'url_path' => $get_question->url_path,
                'file_size' => $get_question->file_size,
                'file_duration' => $get_question->file_duration,
                'type' => $get_question->type,
                'hint' => $get_question->hint,
                'answers' => $answers,
            ];
        }

        $numberedData = new Collection();
        $cellStartAt = count($this->headings) + 1;
        foreach ($questions as $key => $item) {
            $contains = str_contains($item['question'], 'img');
            if ($contains) {
                $this->rowPosition[] = $key + $cellStartAt;
                $currNumber = (string)$key + $cellStartAt;
                $this->imagesQuestion["B" . $currNumber] = $item['question'];
            }

            $filter_selected_answer = count($item['answers']) > 0 ? array_filter($item['answers'], fn($answer) => $answer['is_selected']) : [];
            $selected_answer = count($filter_selected_answer) > 0 ? array_values($filter_selected_answer)[0] : [];
            $sanitizedAnswer = $selected_answer ? html_entity_decode(strip_tags($selected_answer['answer'])) : "";

            if ($selected_answer) {
                if (str_contains($selected_answer['answer'], 'img')) {
                    $this->rowPositionAnswer[] = $key + $cellStartAt;
                    $currNumber = (string)$key + $cellStartAt;
                    $this->imagesAnswer["C" . $currNumber] = $selected_answer['answer'];
                }
            }
            $sanitizedQuestion = html_entity_decode(strip_tags($item['question']));
            $numberedData->push([
                'No.' => $key + 1,
                'Soal' => $sanitizedQuestion,
                'Jawaban' =>  $sanitizedAnswer,
            ]);
        }

        return $numberedData;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     *
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Kolom A sampai Z akan memiliki word-wrap
            'A:Z' => [
                'alignment' => ['wrapText' => true],
            ],
        ];
    }
    public function title(): string
    {
        return $this->title;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, // Lebar kolom A
            'B' => 200, // Lebar kolom B
            'C' => 15, // Lebar kolom C
        ];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->imagesQuestion as $index => $imgTag) {

            // Ambil src dari tag img
            preg_match('/src="([^"]+)"/', $imgTag, $matches);
            $base64 = $matches[1] ?? null;

            if ($base64 && str_contains($base64, 'base64,')) {

                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $tempFilePath = storage_path('app/temp_image_' . $index . '.png');
                file_put_contents($tempFilePath, $imageData);
                $drawing = new Drawing();
                $imgTag = strip_tags($imgTag);
                // if ($index == "B63") {
                //     $index = "B1";
                // }
                $imgTag = html_entity_decode($imgTag);
                $drawing->setName($imgTag);
                $drawing->setDescription("Image for row $index");
                $drawing->setPath($tempFilePath);
                $drawing->setHeight(50);
                $drawing->setCoordinates($index ?? 'B1');

                $drawings[] = $drawing;
            }
        }

        foreach ($this->imagesAnswer as $index => $imgTag) {

            // Ambil src dari tag img
            preg_match('/src="([^"]+)"/', $imgTag, $matches);
            $base64 = $matches[1] ?? null;

            if ($base64 && str_contains($base64, 'base64,')) {

                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $tempFilePath = storage_path('app/temp_image_' . $index . '.png');
                file_put_contents($tempFilePath, $imageData);

                $drawing = new Drawing();
                $imgTag = strip_tags($imgTag);
                $imgTag = html_entity_decode($imgTag);
                $drawing->setName($imgTag);
                $drawing->setDescription("Image for row $index");
                $drawing->setPath($tempFilePath);
                $drawing->setHeight(50);

                $drawing->setCoordinates($index ?? 'C1');

                $drawings[] = $drawing;
            }
        }
        // $this->imagesQuestion = [];
        // $this->imagesAnswer = [];
        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->setCellValue('B1', "Nama : " . $this->name);
                $event->sheet->mergeCells('A1:B1');
                $event->sheet->setCellValue('B2', "Test : " . $this->test ? $this->test->name : "");
                $event->sheet->mergeCells('A2:B2');
                $event->sheet->setCellValue('B3', "Percobaan : " . $this->attempt);
                $event->sheet->mergeCells('A3:B3');
                $event->sheet->setCellValue('B4', "Skor : " . $this->score);
                $event->sheet->mergeCells('A4:B4');
                /** @var Worksheet $sheet */
                for ($index = 0; $index < count($this->rowPosition); $index++) {
                    $text = $event->sheet->getCell('B' . $this->rowPosition[$index])->getValue();
                    $length = strlen($text) > 0 ? strlen($text) + 50 : 100; // bersihkan HTML kalau ada
                    $charsPerLine = 28;
                    $lines = ceil($length / $charsPerLine);
                    $height = $lines * 15;
                    // $drawing->setHeight($height);
                    $event->sheet->getDelegate()->getRowDimension($this->rowPosition[$index])->setRowHeight($height);  // Single row
                }
            },
        ];
    }
}
