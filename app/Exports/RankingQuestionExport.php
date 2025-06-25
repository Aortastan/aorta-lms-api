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




class RankingQuestionExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithDrawings, WithEvents
{
    /**
     * @return \Illuminate\Support\Collection
     */

     protected $tryout_uuid;
     protected $sortBy;
     protected $images = [];
     protected $rowPosition = [];
 
     public function __construct($tryout_uuid, $sortBy)
     {
         $this->tryout_uuid = $tryout_uuid;
         $this->sortBy = $sortBy;
     }
    public function collection()
    {
          $tryout_uuid = $this->tryout_uuid;
          $sortBy = $this->sortBy;
          $tryout = Tryout::where('uuid', $tryout_uuid)
          ->with(['tryoutSegments.tryoutSegmentTests.studentTryouts'])
          ->first();

      if (!$tryout) {
          return response()->json([
              'message' => "Tes tidak ditemukan",
          ], 404);
      }

      $studentTryouts = $tryout->tryoutSegments->flatMap->tryoutSegmentTests->flatMap->studentTryouts;

      $studentTryouts = $studentTryouts->map(function ($item) {
          $item->data_question = json_decode($item->data_question, true);
          return $item;
      });

      $summary = collect();
      $number = 1;
      foreach ($studentTryouts as $tryout) {
          foreach ($tryout->data_question ?? [] as $key => $question) {
              if (!isset($question['question_uuid'])) {
                  continue;
              }

              $questionUuid = $question['question_uuid'];
              $answers = collect($question['answers'] ?? []);

              $isCorrectSelected = $answers->where('is_correct', 1)->where('is_selected', 1)->count();
              $isIncorrectSelected = $answers->where('is_correct', 0)->where('is_selected', 1)->count();

              if (!$summary->has($questionUuid)) {
                  $summary->put($questionUuid, [
                      'No.' => $number++,
                      'Soal' => Question::where('uuid', $questionUuid)->first()->question,
                      'Jumlah Jawaban Benar' => 0,
                      'Jumlah Jawaban Salah' => 0,
                  ]);
              }

              $current = $summary->get($questionUuid);
              
              $current['Jumlah Jawaban Benar'] += $isCorrectSelected;
              $current['Jumlah Jawaban Salah'] += $isIncorrectSelected;
              $contains = str_contains($current['Soal'], 'img');
              if($contains) {
                $this->rowPosition[] = $current['No.'] + 1;
                $currNumber = (string)$current['No.'] + 1;
                $this->images["B" . $currNumber] = $current['Soal'];
              }

              $current['Soal'] = strip_tags($current['Soal']);
              $current['Soal'] = html_entity_decode($current['Soal']);
              $current['Jumlah Jawaban Benar'] = (string)$current['Jumlah Jawaban Benar'];
              $current['Jumlah Jawaban Salah'] = (string)$current['Jumlah Jawaban Salah'];
              $summary->put($questionUuid, $current);
          }
      }

      // Sorting logic
    $sorted = $summary->values();

    // switch ($sortBy) {
    //   case 'wrong_desc': // Soal salah terbanyak ke paling sedikit
    //       $sorted = $sorted->sortByDesc('total_incorrect_selected')->values();
    //       break;
    //   case 'correct_desc': // Soal benar terbanyak ke paling sedikit
    //       $sorted = $sorted->sortByDesc('total_correct_selected')->values();
    //       break;
    //   case 'wrong_asc': // Soal salah paling sedikit ke paling banyak
    //       $sorted = $sorted->sortBy('total_incorrect_selected')->values();
    //       break;
    //   case 'correct_asc': // Soal benar paling sedikit ke paling banyak
    //       $sorted = $sorted->sortBy('total_correct_selected')->values();
    //       break;
    //   default:
    //       // Default tetap wrong_desc
    //       $sorted = $sorted->sortByDesc('total_incorrect_selected')->values();
    //       break;
    // }

        return $sorted;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'No.',
            'Soal',
            'Jumlah Jawaban Benar',
            'Jumlah Jawaban Salah',
        ];
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

    public function columnWidths(): array
    {
        return [
            'A' => 5, // Lebar kolom A
            'B' => 200, // Lebar kolom B
            'C' => 15, // Lebar kolom C
            'D' => 15, // Lebar kolom D
        ];
    }

    public function drawings()
    {
        $drawings = [];

        foreach ($this->images as $index => $imgTag) {

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
                $drawing->setCoordinates($index ?? 'A1');

                $drawings[] = $drawing;
            }
        }
        $this->images = [];
        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                /** @var Worksheet $sheet */
                for($index = 0; $index < count($this->rowPosition); $index++) {
                    $text = $event->sheet->getCell('B' . $this->rowPosition[$index])->getValue();
                    $length = strlen($text) > 0 ? strlen($text) + 50 : 100; // bersihkan HTML kalau ada
                    $charsPerLine = 28;
                    $lines = ceil($length / $charsPerLine);
                    $height = $lines * 15;
                     $event->sheet->getDelegate()->getRowDimension($this->rowPosition[$index])->setRowHeight($height);  // Single row
                  }
            },
        ];
    }
}

