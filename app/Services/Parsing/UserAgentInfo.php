<?php

declare(strict_types=1);

namespace App\Services\Parsing;

final class UserAgentInfo
{
    public const OS_WINDOWS = 'windows';
    public const OS_MACOS = 'macos';
    public const OS_LINUX = 'linux';
    public const OS_ANDROID = 'android';
    public const OS_IOS = 'ios';
    public const OS_OTHER = 'other';

    public const ARCH_X86 = 'x86';
    public const ARCH_X64 = 'x64';
    public const ARCH_UNKNOWN = 'unknown';

    public const BROWSER_CHROME = 'chrome';
    public const BROWSER_FIREFOX = 'firefox';
    public const BROWSER_SAFARI = 'safari';
    public const BROWSER_EDGE = 'edge';
    public const BROWSER_OPERA = 'opera';
    public const BROWSER_YANDEX = 'yandex';
    public const BROWSER_IE = 'ie';
    public const BROWSER_OTHER = 'other';

    public function __construct(
        public readonly string $os,
        public readonly string $arch,
        public readonly string $browser,
        public readonly bool $isBot,
        public readonly ?string $botName = null,
    ) {}
}
