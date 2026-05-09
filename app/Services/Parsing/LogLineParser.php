<?php

declare(strict_types=1);

namespace App\Services\Parsing;

use DateTimeImmutable;

final class LogLineParser
{
    private const REGEX = '~^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"([A-Z]+)\s+(\S+)(?:\s+HTTP/[\d.]+)?"\s+(\d{3})\s+(\d+|-)\s+"([^"]*)"\s+"([^"]*)"\s*$~';

    private const DATE_FORMAT = 'd/M/Y:H:i:s O';

    private const URL_MAX = 2048;
    private const UA_MAX = 1024;
    private const REFERER_MAX = 1024;

    private const STATUS_MIN = 100;
    private const STATUS_MAX = 999;

    private UserAgentParser $userAgentParser;

    public function __construct(?UserAgentParser $userAgentParser = null)
    {
        $this->userAgentParser = $userAgentParser ?? new UserAgentParser();
    }

    public function parse(string $line): ?ParsedLogLine
    {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return null;
        }

        if (preg_match(self::REGEX, $line, $matches) !== 1) {
            return null;
        }

        $ip = $matches[1];
        if (!$this->isValidIp($ip)) {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $matches[2]);
        if ($dateTime === false) {
            return null;
        }

        $httpStatus = (int) $matches[5];
        if ($httpStatus < self::STATUS_MIN || $httpStatus > self::STATUS_MAX) {
            return null;
        }

        $responseSize = $matches[6] === '-' ? null : (int)$matches[6];

        $method       = strtoupper($matches[3]);
        $url          = $this->cut($matches[4], self::URL_MAX);
        $userAgent    = $this->cut($matches[8], self::UA_MAX);
        $referer      = $this->normalizeReferer($matches[7]);

        return new ParsedLogLine(
            ip: $ip,
            requestedAt: $dateTime,
            method: $method,
            url: $url,
            httpStatus: $httpStatus,
            responseSize: $responseSize,
            referer: $referer,
            userAgent: $userAgent,
            ua: $this->userAgentParser->parse($userAgent),
        );
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function normalizeReferer(string $referer): ?string
    {
        if ($referer === '' || $referer === '-') {
            return null;
        }
        return $this->cut($referer, self::REFERER_MAX);
    }

    private function cut(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }
        return substr($value, 0, $maxLength);
    }
}
