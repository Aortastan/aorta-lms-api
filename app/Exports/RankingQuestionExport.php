<?php

namespace App\Exports;

use App\Models\Tryout;
use App\Models\Question;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Support\Facades\DB;

class RankingQuestionExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithDrawings, WithEvents
{
    protected $tryout_uuid;
    protected $sortBy;
    protected $segmentTestUuid;
    protected $images = [];
    protected $rowPosition = [];

    public function __construct($tryout_uuid, $sortBy = null, $segmentTestUuid = null)
    {
        $this->tryout_uuid = $tryout_uuid;
        $this->sortBy = $sortBy;
        $this->segmentTestUuid = $segmentTestUuid;
    }

    public function collection()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $tryout_uuid = $this->tryout_uuid;
        $sortBy = $this->sortBy;
        $segmentTestUuid = $this->segmentTestUuid;

        $tryout = Tryout::where('uuid', $tryout_uuid)
            ->with([
                'tryoutSegments.tryoutSegmentTests' => function ($q) use ($segmentTestUuid) {
                    if (!empty($segmentTestUuid)) {
                        $q->where('uuid', $segmentTestUuid);
                    }
                },
                'tryoutSegments.tryoutSegmentTests.test'
            ])
            ->first();

        if (!$tryout) {
            return collect();
        }

        $segmentTests = $tryout->tryoutSegments->flatMap->tryoutSegmentTests;
        $segmentTestUuids = $segmentTests->pluck('uuid')->toArray();

        if (empty($segmentTestUuids)) {
            return collect();
        }

        // Ambil urutan nomor soal dari question_tests
        $testUuids = $segmentTests->pluck('test_uuid')->filter()->unique()->toArray();
        $questionTests = DB::table('question_tests')
            ->whereIn('test_uuid', $testUuids)
            ->orderBy('id', 'asc')
            ->select('test_uuid', 'question_uuid', 'id')
            ->get();

        $questionNumberByTest = [];
        foreach ($questionTests->groupBy('test_uuid') as $tUuid => $qItems) {
            $no = 1;
            foreach ($qItems as $qItem) {
                if (!isset($questionNumberByTest[$tUuid][$qItem->question_uuid])) {
                    $questionNumberByTest[$tUuid][$qItem->question_uuid] = $no++;
                }
            }
        }

        $segmentTestInfo = [];
        foreach ($segmentTests as $st) {
            $segmentTestInfo[$st->uuid] = [
                'test_uuid' => $st->test_uuid,
            ];
        }

        $summary = [];
        $allQuestionUuids = [];

        // Chunking untuk mencegah memory exhausted pada dataset attempt besar (~60MB JSON)
        DB::table('student_tryouts')
            ->whereIn('package_test_uuid', $segmentTestUuids)
            ->whereNotNull('data_question')
            ->select('id', 'package_test_uuid', 'data_question')
            ->orderBy('id')
            ->chunk(150, function ($rows) use (&$summary, &$allQuestionUuids, $segmentTestInfo, $questionNumberByTest) {
                foreach ($rows as $stAttempt) {
                    $decoded = json_decode($stAttempt->data_question, true);
                    if (!is_array($decoded)) {
                        continue;
                    }

                    $packageTestUuid = $stAttempt->package_test_uuid;
                    $tUuid = $segmentTestInfo[$packageTestUuid]['test_uuid'] ?? null;

                    foreach ($decoded as $questionIndex => $question) {
                        if (!isset($question['question_uuid'])) {
                            continue;
                        }

                        $questionUuid = $question['question_uuid'];
                        $allQuestionUuids[$questionUuid] = true;

                        $isCorrectSelected = 0;
                        $isIncorrectSelected = 0;

                        foreach ($question['answers'] ?? [] as $ans) {
                            $isCorrect = isset($ans['is_correct']) && (int)$ans['is_correct'] === 1;
                            $isSelected = isset($ans['is_selected']) && (int)$ans['is_selected'] === 1;

                            if ($isSelected) {
                                if ($isCorrect) {
                                    $isCorrectSelected++;
                                } else {
                                    $isIncorrectSelected++;
                                }
                            }
                        }

                        $qNumber = null;
                        if ($tUuid && isset($questionNumberByTest[$tUuid][$questionUuid])) {
                            $qNumber = $questionNumberByTest[$tUuid][$questionUuid];
                        } else {
                            $qNumber = $questionIndex + 1;
                        }

                        if (!isset($summary[$questionUuid])) {
                            $summary[$questionUuid] = [
                                'No.' => $qNumber,
                                'question_uuid' => $questionUuid,
                                'raw_question' => '',
                                'Soal' => '',
                                'Jumlah Jawaban Benar' => 0,
                                'Jumlah Jawaban Salah' => 0,
                            ];
                        }

                        $summary[$questionUuid]['Jumlah Jawaban Benar'] += $isCorrectSelected;
                        $summary[$questionUuid]['Jumlah Jawaban Salah'] += $isIncorrectSelected;
                    }
                }
            });

        if (!empty($allQuestionUuids)) {
            $questionMap = Question::whereIn('uuid', array_keys($allQuestionUuids))
                ->pluck('question', 'uuid')
                ->toArray();

            foreach ($summary as $qUuid => &$current) {
                $rawQuestion = $questionMap[$qUuid] ?? '';
                $current['raw_question'] = $rawQuestion;
                $cleanQuestion = html_entity_decode(strip_tags($rawQuestion));
                $current['Soal'] = $cleanQuestion;
                $current['Jumlah Jawaban Benar'] = (string)$current['Jumlah Jawaban Benar'];
                $current['Jumlah Jawaban Salah'] = (string)$current['Jumlah Jawaban Salah'];
            }
            unset($current);
        }

        $sorted = collect(array_values($summary));

        switch ($sortBy) {
            case 'wrong_desc':
                $sorted = $sorted->sortByDesc(function ($item) {
                    return (int)$item['Jumlah Jawaban Salah'];
                })->values();
                break;
            case 'correct_desc':
                $sorted = $sorted->sortByDesc(function ($item) {
                    return (int)$item['Jumlah Jawaban Benar'];
                })->values();
                break;
            case 'wrong_asc':
                $sorted = $sorted->sortBy(function ($item) {
                    return (int)$item['Jumlah Jawaban Salah'];
                })->values();
                break;
            case 'correct_asc':
                $sorted = $sorted->sortBy(function ($item) {
                    return (int)$item['Jumlah Jawaban Benar'];
                })->values();
                break;
            case 'number_desc':
                $sorted = $sorted->sortByDesc('No.')->values();
                break;
            case 'number_asc':
            default:
                $sorted = $sorted->sortBy('No.')->values();
                break;
        }

        // Simpan posisi baris dan gambar berdasarkan urutan final baris excel (dimulai dari baris 2 setelah header)
        $this->images = [];
        $this->rowPosition = [];

        $finalCollection = new Collection();
        foreach ($sorted as $rowIndex => $item) {
            $excelRowNum = $rowIndex + 2; // Row 1 is header
            $rawQuestion = $item['raw_question'] ?? '';

            if (str_contains($rawQuestion, 'img')) {
                $this->rowPosition[] = $excelRowNum;
                $this->images["B" . $excelRowNum] = $rawQuestion;
            }

            $finalCollection->push([
                'No.' => $item['No.'],
                'Soal' => $item['Soal'],
                'Jumlah Jawaban Benar' => $item['Jumlah Jawaban Benar'],
                'Jumlah Jawaban Salah' => $item['Jumlah Jawaban Salah'],
            ]);
        }

        return $finalCollection;
    }

    public function headings(): array
    {
        return [
            'No.',
            'Soal',
            'Jumlah Jawaban Benar',
            'Jumlah Jawaban Salah',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            'A:Z' => [
                'alignment' => ['wrapText' => true],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 200,
            'C' => 15,
            'D' => 15,
        ];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->images as $coordinate => $imgTag) {
            preg_match('/src="([^"]+)"/', $imgTag, $matches);
            $base64 = $matches[1] ?? null;

            if ($base64 && str_contains($base64, 'base64,')) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $tempFilePath = storage_path('app/temp_image_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $coordinate) . '.png');
                file_put_contents($tempFilePath, $imageData);

                $drawing = new Drawing();
                $cleanTag = html_entity_decode(strip_tags($imgTag));
                $drawing->setName($cleanTag ?: "Image");
                $drawing->setDescription("Image for row $coordinate");
                $drawing->setPath($tempFilePath);
                $drawing->setHeight(50);
                $drawing->setCoordinates($coordinate);

                $drawings[] = $drawing;
            }
        }
        $this->images = [];
        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                /** @var Worksheet $sheet */
                foreach ($this->rowPosition as $rowNum) {
                    $text = $event->sheet->getCell('B' . $rowNum)->getValue();
                    $length = strlen($text) > 0 ? strlen($text) + 50 : 100;
                    $charsPerLine = 28;
                    $lines = ceil($length / $charsPerLine);
                    $height = $lines * 15;
                    $event->sheet->getDelegate()->getRowDimension($rowNum)->setRowHeight($height);
                }
            },
        ];
    }
}
