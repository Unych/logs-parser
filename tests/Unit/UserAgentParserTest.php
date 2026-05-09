<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Parsing\UserAgentInfo;
use App\Services\Parsing\UserAgentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserAgentParser::class)]
final class UserAgentParserTest extends TestCase
{
    private UserAgentParser $parser;

    protected function setUp(): void
    {
        $this->parser = new UserAgentParser();
    }

    #[DataProvider('osProvider')]
    public function test_detects_os(string $ua, string $expected): void
    {
        self::assertSame($expected, $this->parser->parse($ua)->os);
    }

    public static function osProvider(): array
    {
        return [
            'windows 10' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                UserAgentInfo::OS_WINDOWS,
            ],
            'macos' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
                UserAgentInfo::OS_MACOS,
            ],
            'iphone' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15',
                UserAgentInfo::OS_IOS,
            ],
            'ipad' => [
                'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15',
                UserAgentInfo::OS_IOS,
            ],
            'android' => [
                'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36',
                UserAgentInfo::OS_ANDROID,
            ],
            'linux' => [
                'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
                UserAgentInfo::OS_LINUX,
            ],
            'unknown' => ['SomeBot/1.0', UserAgentInfo::OS_OTHER],
            'empty' => ['', UserAgentInfo::OS_OTHER],
        ];
    }

    #[DataProvider('archProvider')]
    public function test_detects_arch(string $ua, string $expected): void
    {
        self::assertSame($expected, $this->parser->parse($ua)->arch);
    }

    public static function archProvider(): array
    {
        return [
            'win64 x64' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', UserAgentInfo::ARCH_X64],
            'wow64' => ['Mozilla/5.0 (Windows NT 10.0; WOW64)', UserAgentInfo::ARCH_X64],
            'linux x86_64' => ['Mozilla/5.0 (X11; Linux x86_64)', UserAgentInfo::ARCH_X64],
            'amd64' => ['Mozilla/5.0 (FreeBSD amd64)', UserAgentInfo::ARCH_X64],
            'win32' => ['Mozilla/5.0 (Windows NT 6.1; Win32)', UserAgentInfo::ARCH_X86],
            'i686' => ['Mozilla/5.0 (X11; Linux i686)', UserAgentInfo::ARCH_X86],
            'android arm' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7)', UserAgentInfo::ARCH_UNKNOWN],
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)', UserAgentInfo::ARCH_UNKNOWN],
            'unknown' => ['Bot/1.0', UserAgentInfo::ARCH_UNKNOWN],
        ];
    }

    #[DataProvider('browserProvider')]
    public function test_detects_browser(string $ua, string $expected): void
    {
        self::assertSame($expected, $this->parser->parse($ua)->browser);
    }

    public static function browserProvider(): array
    {
        return [
            'chrome' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                UserAgentInfo::BROWSER_CHROME,
            ],
            'firefox' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
                UserAgentInfo::BROWSER_FIREFOX,
            ],
            'safari' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
                UserAgentInfo::BROWSER_SAFARI,
            ],
            'edge' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0',
                UserAgentInfo::BROWSER_EDGE,
            ],
            'opera' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/110.0.0.0',
                UserAgentInfo::BROWSER_OPERA,
            ],
            'ie11' => [
                'Mozilla/5.0 (Windows NT 10.0; Trident/7.0; rv:11.0) like Gecko',
                UserAgentInfo::BROWSER_IE,
            ],
            'chrome ios' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) CriOS/126.0.0.0 Mobile/15E148 Safari/604.1',
                UserAgentInfo::BROWSER_CHROME,
            ],
            'unknown' => ['SomeUnknown/1.0', UserAgentInfo::BROWSER_OTHER],
        ];
    }

    public function test_bot_marks_browser_as_other(): void
    {
        $info = $this->parser->parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        self::assertTrue($info->isBot);
        self::assertSame('Googlebot', $info->botName);
        self::assertSame(UserAgentInfo::BROWSER_OTHER, $info->browser);
    }

    public function test_empty_user_agent_is_not_a_bot(): void
    {
        $info = $this->parser->parse('');

        self::assertFalse($info->isBot);
        self::assertNull($info->botName);
        self::assertSame(UserAgentInfo::BROWSER_OTHER, $info->browser);
    }

    public function test_dash_user_agent_is_not_a_bot(): void
    {
        $info = $this->parser->parse('-');

        self::assertFalse($info->isBot);
        self::assertNull($info->botName);
    }

    public function test_chromium_based_browsers_detected_in_correct_order(): void
    {
        $edgeUa   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0';
        $operaUa  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/110.0.0.0';
        $chromeUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

        $edge   = $this->parser->parse($edgeUa);
        $opera  = $this->parser->parse($operaUa);
        $chrome = $this->parser->parse($chromeUa);

        self::assertSame(UserAgentInfo::BROWSER_EDGE, $edge->browser);
        self::assertSame(UserAgentInfo::BROWSER_OPERA, $opera->browser);
        self::assertSame(UserAgentInfo::BROWSER_CHROME, $chrome->browser);
    }
}
