<?php

declare(strict_types=1);

namespace App\Services\Parsing;

final class UserAgentParser
{
    private const KNOWN_BOTS = [
        'googlebot'           => 'Googlebot',
        'yandexbot'           => 'YandexBot',
        'yandeximages'        => 'YandexImages',
        'bingbot'             => 'Bingbot',
        'duckduckbot'         => 'DuckDuckBot',
        'baiduspider'         => 'Baiduspider',
        'slurp'               => 'Yahoo! Slurp',
        'facebookexternalhit' => 'FacebookExternalHit',
        'facebot'             => 'Facebot',
        'twitterbot'          => 'Twitterbot',
        'applebot'            => 'Applebot',
        'ahrefsbot'           => 'AhrefsBot',
        'semrushbot'          => 'SemrushBot',
        'mj12bot'             => 'MJ12bot',
        'dotbot'              => 'DotBot',
        'sogou'               => 'Sogou',
        'exabot'              => 'Exabot',
        'petalbot'            => 'PetalBot',
        'mailru'              => 'Mail.RU_Bot',
    ];

    public function parse(string $userAgent): UserAgentInfo
    {
        $trimmedUserAgent = trim($userAgent);

        $botName = $this->detectBotName($trimmedUserAgent);
        $isBot   = $botName !== null;

        return new UserAgentInfo(
            os: $this->detectOs($trimmedUserAgent),
            arch: $this->detectArch($trimmedUserAgent),
            browser: $isBot ? UserAgentInfo::BROWSER_OTHER : $this->detectBrowser($trimmedUserAgent),
            isBot: $isBot,
            botName: $botName,
        );
    }

    private function detectBotName(string $userAgent): ?string
    {
        if ($userAgent === '' || $userAgent === '-') {
            return null;
        }

        $lowerCaseUserAgent = strtolower($userAgent);

        foreach (self::KNOWN_BOTS as $keyword => $botName) {
            if (str_contains($lowerCaseUserAgent, $keyword)) {
                return $botName;
            }
        }

        if (preg_match('/(bot|crawler|spider)/', $lowerCaseUserAgent) === 1) {
            return 'Generic Bot';
        }

        return null;
    }

    private function detectOs(string $userAgent): string
    {
        if (preg_match('/\b(iPhone|iPad|iPod|iOS)\b/', $userAgent) === 1) {
            return UserAgentInfo::OS_IOS;
        }

        if (str_contains($userAgent, 'Android')) {
            return UserAgentInfo::OS_ANDROID;
        }

        if (preg_match('/Windows (NT|95|98|2000|XP|Phone)/', $userAgent) === 1) {
            return UserAgentInfo::OS_WINDOWS;
        }

        if (str_contains($userAgent, 'Mac OS X') || str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'macOS')) {
            return UserAgentInfo::OS_MACOS;
        }

        if (str_contains($userAgent, 'Linux') || str_contains($userAgent, 'X11') || str_contains($userAgent, 'Ubuntu')) {
            return UserAgentInfo::OS_LINUX;
        }

        return UserAgentInfo::OS_OTHER;
    }

    private function detectArch(string $userAgent): string
    {
        if (preg_match('/(x86_64|x64|Win64|WOW64|amd64|x86-64)/i', $userAgent) === 1) {
            return UserAgentInfo::ARCH_X64;
        }

        if (preg_match('/(i[3-6]86|Win32|x86(?!_64)(?!-64))/', $userAgent) === 1) {
            return UserAgentInfo::ARCH_X86;
        }

        return UserAgentInfo::ARCH_UNKNOWN;
    }

    private function detectBrowser(string $userAgent): string
    {
        if (str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'Edge/')) {
            return UserAgentInfo::BROWSER_EDGE;
        }

        if (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            return UserAgentInfo::BROWSER_OPERA;
        }

        if (str_contains($userAgent, 'Firefox/')) {
            return UserAgentInfo::BROWSER_FIREFOX;
        }

        if (str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/')) {
            return UserAgentInfo::BROWSER_CHROME;
        }

        if (preg_match('#Version/[\d.]+.*Safari/[\d.]+#', $userAgent) === 1 || str_contains($userAgent, 'Safari/')) {
            return UserAgentInfo::BROWSER_SAFARI;
        }

        if (preg_match('#(MSIE [\d.]+|Trident/[\d.]+)#', $userAgent) === 1) {
            return UserAgentInfo::BROWSER_IE;
        }

        return UserAgentInfo::BROWSER_OTHER;
    }
}
