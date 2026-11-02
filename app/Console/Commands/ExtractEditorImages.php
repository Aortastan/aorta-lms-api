<?php

namespace App\Console\Commands;

use App\Services\EditorImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExtractEditorImages extends Command
{
    const TARGETS = [
        'questions' => ['question', 'reference', 'discussion', 'hint'],
        'answers' => ['answer', 'correct_answer_explanation'],
    ];
    const BACKUP_FOLDER = 'editor-image-backups';
    const CHUNK = 50;

    protected $signature = 'editor-images:extract
        {--dry-run : Hitung saja tanpa menyimpan file maupun mengubah database}
        {--table= : Batasi ke satu tabel (questions atau answers)}
        {--limit=0 : Batasi jumlah baris yang diproses per tabel}
        {--test=* : Batasi ke soal dalam tes tertentu (uuid atau judul persis)}
        {--restore= : Kembalikan isi kolom dari file backup JSONL}';

    protected $description = 'Pindahkan gambar base64 di soal dan jawaban menjadi file di storage';

    private $cache = [];
    private $stats = [];
    private $backup;

    public function handle()
    {
        if ($restore = $this->option('restore')) {
            return $this->restore($restore);
        }

        $dryRun = (bool) $this->option('dry-run');
        $targets = self::TARGETS;
        if ($table = $this->option('table')) {
            if (!isset($targets[$table])) {
                $this->error('Tabel harus questions atau answers');
                return 1;
            }
            $targets = [$table => $targets[$table]];
        }

        $questionUuids = null;
        if ($tests = $this->option('test')) {
            $testUuids = DB::table('tests')
                ->whereIn('uuid', $tests)
                ->orWhereIn('title', $tests)
                ->pluck('uuid', 'title');
            if ($testUuids->isEmpty()) {
                $this->error('Tes tidak ditemukan');
                return 1;
            }
            foreach ($testUuids as $title => $uuid) {
                $this->info("Tes: {$title} ({$uuid})");
            }
            $questionUuids = DB::table('question_tests')
                ->whereIn('test_uuid', $testUuids->values())
                ->pluck('question_uuid')
                ->unique()
                ->values()
                ->all();
        }

        if (!$dryRun) {
            $path = self::BACKUP_FOLDER . '/extract-' . date('Ymd-His') . '.jsonl';
            Storage::disk('local')->put($path, '');
            $this->backup = fopen(Storage::disk('local')->path($path), 'a');
            $this->info('Backup: ' . Storage::disk('local')->path($path));
        }

        foreach ($targets as $table => $columns) {
            $this->processTable($table, $columns, $dryRun, (int) $this->option('limit'), $questionUuids);
        }

        if ($this->backup) {
            fclose($this->backup);
        }

        $this->report($dryRun);

        return 0;
    }

    private function processTable(string $table, array $columns, bool $dryRun, int $limit, ?array $questionUuids)
    {
        $stats = [
            'rows' => 0,
            'updated' => 0,
            'skipped_changed' => 0,
            'images' => 0,
            'failed' => 0,
            'before' => 0,
            'after' => 0,
        ];

        $query = DB::table($table)
            ->select(array_merge(['id', 'updated_at'], $columns))
            ->where(function ($query) use ($columns) {
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%data:image%');
                }
            });
        if ($questionUuids !== null) {
            $query->whereIn($table === 'questions' ? 'uuid' : 'question_uuid', $questionUuids);
        }

        $bar = $this->output->createProgressBar((clone $query)->count());
        $bar->start();

        $query->chunkById(self::CHUNK, function ($rows) use ($table, $columns, $dryRun, $limit, &$stats, $bar) {
            foreach ($rows as $row) {
                if ($limit > 0 && $stats['rows'] >= $limit) {
                    return false;
                }
                $stats['rows']++;

                $changes = [];
                $originals = [];
                foreach ($columns as $column) {
                    $html = $row->{$column};
                    if (!is_string($html) || strpos($html, 'data:image') === false) {
                        continue;
                    }
                    $converted = $this->convertHtml($html, $dryRun, $stats);
                    $stats['before'] += strlen($html);
                    $stats['after'] += strlen($converted);
                    if ($converted !== $html) {
                        $changes[$column] = $converted;
                        $originals[$column] = $html;
                    }
                }

                if ($changes && !$dryRun) {
                    fwrite($this->backup, json_encode([
                        'table' => $table,
                        'id' => $row->id,
                        'columns' => $originals,
                    ]) . "\n");

                    $updated = DB::table($table)
                        ->where('id', $row->id)
                        ->where(function ($query) use ($row) {
                            $row->updated_at === null
                                ? $query->whereNull('updated_at')
                                : $query->where('updated_at', $row->updated_at);
                        })
                        ->update($changes);

                    $updated ? $stats['updated']++ : $stats['skipped_changed']++;
                } elseif ($changes) {
                    $stats['updated']++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->line('');
        $this->stats[$table] = $stats;
    }

    private function convertHtml(string $html, bool $dryRun, array &$stats): string
    {
        return preg_replace_callback(EditorImage::DATA_URI_PATTERN, function ($match) use ($dryRun, &$stats) {
            $stats['images']++;
            $hash = sha1($match[1]);
            if (!isset($this->cache[$hash])) {
                $binary = EditorImage::decodeDataUri($match[1]);
                $stored = $binary === null ? null : EditorImage::store($binary, !$dryRun);
                $this->cache[$hash] = $stored;
            }

            $stored = $this->cache[$hash];
            if (!$stored) {
                $stats['failed']++;
                return $match[0];
            }

            return $stored['url'];
        }, $html);
    }

    private function report(bool $dryRun)
    {
        $files = array_filter($this->cache);
        $fileBytes = array_sum(array_column($files, 'bytes'));
        $originalBytes = array_sum(array_column($files, 'original_bytes'));

        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [
                $table,
                $stats['rows'],
                $stats['updated'],
                $stats['skipped_changed'],
                $stats['images'],
                $stats['failed'],
                $this->mb($stats['before']),
                $this->mb($stats['after']),
            ];
        }

        $this->table(
            ['Tabel', 'Baris', $dryRun ? 'Akan diubah' : 'Diubah', 'Dilewati (diedit)', 'Gambar', 'Gagal', 'HTML sebelum', 'HTML sesudah'],
            $rows
        );
        $this->info(sprintf(
            'File gambar unik: %d (asli %s, setelah kompres %s)%s',
            count($files),
            $this->mb($originalBytes),
            $this->mb($fileBytes),
            $dryRun ? ' - dry run, tidak ada yang disimpan' : ''
        ));
    }

    private function restore(string $path)
    {
        if (!is_file($path)) {
            $path = Storage::disk('local')->path($path);
        }
        if (!is_file($path)) {
            $this->error('File backup tidak ditemukan');
            return 1;
        }

        $handle = fopen($path, 'r');
        $restored = 0;
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (!$entry || !isset(self::TARGETS[$entry['table']])) {
                continue;
            }
            $columns = array_intersect_key($entry['columns'], array_flip(self::TARGETS[$entry['table']]));
            if ($columns) {
                DB::table($entry['table'])->where('id', $entry['id'])->update($columns);
                $restored++;
            }
        }
        fclose($handle);

        $this->info("Baris dikembalikan: {$restored}");

        return 0;
    }

    private function mb(int $bytes): string
    {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
}
