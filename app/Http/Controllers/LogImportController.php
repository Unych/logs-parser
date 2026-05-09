<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadLogRequest;
use App\Models\ImportJob;
use App\Services\Queue\RedisLogQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LogImportController extends Controller
{
    public function __construct(
        private readonly RedisLogQueue $queue,
    ) {}

    public function store(UploadLogRequest $request): JsonResponse
    {
        $file = $request->file('log_file');

        $hash = (string) hash_file('sha256', $file->getRealPath());

        $existing = ImportJob::query()
            ->where('file_hash', $hash)
            ->whereIn('status', [
                ImportJob::STATUS_PENDING,
                ImportJob::STATUS_PROCESSING,
                ImportJob::STATUS_DONE,
            ])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return response()->json([
                'job_id'    => $existing->id,
                'status'    => $existing->status,
                'duplicate' => true,
            ]);
        }

        $disk = Storage::disk('uploads');
        $storedName = $hash.'.log';
        $disk->putFileAs('', $file, $storedName);

        $job = ImportJob::create([
            'file_hash'     => $hash,
            'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
            'stored_path'   => $disk->path($storedName),
            'file_size'     => (int) $file->getSize(),
            'status'        => ImportJob::STATUS_PENDING,
        ]);

        $this->queue->push($job->id);

        return response()->json([
            'job_id'    => $job->id,
            'status'    => $job->status,
            'duplicate' => false,
        ], 201);
    }

    public function progress(ImportJob $job): JsonResponse
    {
        $live = $this->queue->getProgress($job->id);
        $processed = $live ?? $job->lines_processed;

        return response()->json([
            'job_id'          => $job->id,
            'status'          => $job->status,
            'lines_processed' => $processed,
            'lines_invalid'   => $job->lines_invalid,
            'error'           => $job->error,
            'finished'        => $job->isFinished(),
        ]);
    }
}
