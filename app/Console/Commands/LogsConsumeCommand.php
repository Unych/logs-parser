<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Services\Importing\LogImporter;
use App\Services\Queue\RedisLogQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LogsConsumeCommand extends Command
{
    protected $signature = 'logs:consume
        {--once : Process at most one job and exit}
        {--max-jobs=0 : Exit after this many jobs (0 = unlimited)}
        {--timeout=5 : BRPOP block timeout in seconds}';

    protected $description = 'Consume the Redis log:queue and import enqueued log files';

    private bool $shouldStop = false;

    public function handle(RedisLogQueue $queue, LogImporter $importer): int
    {
        $this->registerSignalHandlers();

        $maxJobs = (int) $this->option('max-jobs');
        $once = (bool) $this->option('once');
        $timeout = max(1, (int) $this->option('timeout'));

        $processed = 0;
        $this->info('Worker started. Waiting for jobs on '.RedisLogQueue::QUEUE_KEY);

        while (!$this->shouldStop) {
            $jobId = $queue->popBlocking($timeout);

            if ($jobId === null) {
                continue;
            }

            $this->processJob($jobId, $importer);
            $processed++;

            if ($once || ($maxJobs > 0 && $processed >= $maxJobs)) {
                break;
            }
        }

        $this->info("Worker stopped after {$processed} job(s).");

        return self::SUCCESS;
    }

    private function processJob(int $jobId, LogImporter $importer): void
    {
        $job = ImportJob::find($jobId);
        if ($job === null) {
            $this->warn("Job #{$jobId} not found, skipping");
            return;
        }

        if ($job->status !== ImportJob::STATUS_PENDING) {
            $this->warn("Job #{$jobId} is in status '{$job->status}', skipping");
            return;
        }

        $this->info("Importing job #{$jobId}: {$job->original_name}");

        try {
            $importer->import($job);
            $this->info("Job #{$jobId} done. Processed: {$job->lines_processed}, invalid: {$job->lines_invalid}");
        } catch (Throwable $e) {
            $this->error("Job #{$jobId} failed: ".$e->getMessage());
            Log::error('Worker import error', [
                'job_id' => $jobId,
                'exception' => $e,
            ]);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $handler = function (int $signal): void {
            $this->info("Received signal {$signal}, shutting down gracefully...");
            $this->shouldStop = true;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}
