<?php

declare(strict_types=1);

namespace App\Services\Importing;

use App\Models\ImportJob;
use App\Models\LogEntry;
use App\Services\Parsing\LogLineParser;
use App\Services\Queue\RedisLogQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class LogImporter
{
    public function __construct(
        private readonly LogLineParser $parser,
        private readonly RedisLogQueue $queue,
        private readonly int $batchSize = 1000,
    ) {}

    public function import(ImportJob $job): void
    {
        if (!is_file($job->stored_path) || !is_readable($job->stored_path)) {
            throw new RuntimeException("Stored file is missing or unreadable: {$job->stored_path}");
        }

        $job->update([
            'status' => ImportJob::STATUS_PROCESSING,
            'started_at' => now(),
            'lines_processed' => 0,
            'lines_invalid' => 0,
        ]);
        $this->queue->setProgress($job->id, 0);

        $handle = fopen($job->stored_path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$job->stored_path}");
        }

        $batch = [];
        $processed = 0;
        $invalid = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $parsed = $this->parser->parse($line);

                if ($parsed === null) {
                    $invalid++;
                    continue;
                }

                $batch[] = $parsed->toRow($job->id);

                if (count($batch) >= $this->batchSize) {
                    $processed += $this->flush($batch);
                    $batch = [];
                    $this->updateProgress($job, $processed, $invalid);
                }
            }

            if ($batch !== []) {
                $processed += $this->flush($batch);
            }

            $this->updateProgress($job, $processed, $invalid);

            $job->update([
                'status' => ImportJob::STATUS_DONE,
                'finished_at' => now(),
                'lines_processed' => $processed,
                'lines_invalid' => $invalid,
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => ImportJob::STATUS_FAILED,
                'error' => substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
                'lines_processed' => $processed,
                'lines_invalid' => $invalid,
            ]);
            Log::error('Log import failed', [
                'job_id' => $job->id,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            fclose($handle);
        }
    }

    private function flush(array $rows): int
    {
        $now = Carbon::now()->toDateTimeString();
        foreach ($rows as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);

        DB::table((new LogEntry)->getTable())->insertOrIgnore($rows);

        return count($rows);
    }

    private function updateProgress(ImportJob $job, int $processed, int $invalid): void
    {
        $this->queue->setProgress($job->id, $processed);
        $job->forceFill([
            'lines_processed' => $processed,
            'lines_invalid' => $invalid,
        ])->saveQuietly();
    }
}
