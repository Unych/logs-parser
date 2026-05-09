<?php

declare(strict_types=1);

namespace App\Services\Queue;

use Illuminate\Contracts\Redis\Connection;
use Illuminate\Support\Facades\Redis;

final class RedisLogQueue
{
    public const QUEUE_KEY = 'log:queue';
    public const PROGRESS_KEY_PREFIX = 'import:progress:';

    public function __construct(
        private readonly ?Connection $connection = null,
    ) {}

    public function push(int $jobId): void
    {
        $payload = json_encode(['job_id' => $jobId], JSON_THROW_ON_ERROR);
        $this->redis()->lpush(self::QUEUE_KEY, $payload);
    }

    public function popBlocking(int $timeoutSeconds = 5): ?int
    {
        $result = $this->redis()->brpop([self::QUEUE_KEY], $timeoutSeconds);
        if ($result === null || $result === []) {
            return null;
        }

        try {
            $payload = json_decode($result[1], true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $jobId = $payload['job_id'] ?? null;
        return is_int($jobId) ? $jobId : null;
    }

    public function size(): int
    {
        return (int) $this->redis()->llen(self::QUEUE_KEY);
    }

    public function setProgress(int $jobId, int $linesProcessed, int $ttlSeconds = 3600): void
    {
        $this->redis()->setex(self::PROGRESS_KEY_PREFIX.$jobId, $ttlSeconds, (string) $linesProcessed);
    }

    public function getProgress(int $jobId): ?int
    {
        $value = $this->redis()->get(self::PROGRESS_KEY_PREFIX.$jobId);
        return $value === null ? null : (int) $value;
    }

    public function clearProgress(int $jobId): void
    {
        $this->redis()->del([self::PROGRESS_KEY_PREFIX.$jobId]);
    }

    private function redis(): Connection
    {
        return $this->connection ?? Redis::connection();
    }
}
