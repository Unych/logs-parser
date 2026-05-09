<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use DateTimeImmutable;

final class ParsedLogLine
{
    public function __construct(
        public readonly string $ip,
        public readonly DateTimeImmutable $requestedAt,
        public readonly string $method,
        public readonly string $url,
        public readonly int $httpStatus,
        public readonly ?int $responseSize,
        public readonly ?string $referer,
        public readonly string $userAgent,
        public readonly UserAgentInfo $ua,
    ) {}

    public function toRow(?int $importJobId = null): array
    {
        return [
            'ip' => $this->ip,
            'requested_at' => $this->requestedAt->format('Y-m-d H:i:s'),
            'url_hash' => md5($this->url),
            'http_status' => $this->httpStatus,
            'ua_hash' => md5($this->userAgent),
            'method' => $this->method,
            'url' => $this->url,
            'response_size' => $this->responseSize,
            'referer' => $this->referer,
            'user_agent' => $this->userAgent,
            'os' => $this->ua->os,
            'arch' => $this->ua->arch,
            'browser' => $this->ua->browser,
            'is_bot' => $this->ua->isBot,
            'bot_name' => $this->ua->botName,
            'import_job_id' => $importJobId,
        ];
    }
}
