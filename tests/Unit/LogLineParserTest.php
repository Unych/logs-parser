<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Parsing\LogLineParser;
use App\Services\Parsing\UserAgentInfo;
use App\Services\Parsing\UserAgentParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogLineParser::class)]
final class LogLineParserTest extends TestCase
{
    private LogLineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LogLineParser(new UserAgentParser());
    }

    public function test_parses_canonical_combined_log_line(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /favicon/favicon-32.png HTTP/1.1" 200 1306 '
              .'"http://modimio.loc/icms/catalog/catalog_edit?id=4" '
              .'"Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"';

        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame('127.0.0.1', $parsed->ip);
        self::assertSame('GET', $parsed->method);
        self::assertSame('/favicon/favicon-32.png', $parsed->url);
        self::assertSame(200, $parsed->httpStatus);
        self::assertSame(1306, $parsed->responseSize);
        self::assertSame('http://modimio.loc/icms/catalog/catalog_edit?id=4', $parsed->referer);
        self::assertStringContainsString('Chrome/73', $parsed->userAgent);
        self::assertSame('2019-03-21 00:20:06', $parsed->requestedAt->format('Y-m-d H:i:s'));
        self::assertSame('+03:00', $parsed->requestedAt->format('P'));
        self::assertSame(UserAgentInfo::OS_LINUX, $parsed->ua->os);
        self::assertSame(UserAgentInfo::ARCH_X64, $parsed->ua->arch);
        self::assertSame(UserAgentInfo::BROWSER_CHROME, $parsed->ua->browser);
        self::assertFalse($parsed->ua->isBot);
    }

    public function test_returns_null_on_empty_line(): void
    {
        self::assertNull($this->parser->parse(''));
        self::assertNull($this->parser->parse("\n"));
        self::assertNull($this->parser->parse("\r\n"));
    }

    public function test_returns_null_on_garbage_line(): void
    {
        self::assertNull($this->parser->parse('this is not a log line'));
        self::assertNull($this->parser->parse('{"json": "object"}'));
    }

    public function test_returns_null_on_invalid_ip(): void
    {
        $line = 'not.an.ip - - [21/Mar/2019:00:20:06 +0300] "GET /a HTTP/1.1" 200 0 "-" "-"';
        self::assertNull($this->parser->parse($line));
    }

    public function test_returns_null_on_invalid_status(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /a HTTP/1.1" 999 0 "-" "-"';
        self::assertNotNull($this->parser->parse($line));
        $bad = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /a HTTP/1.1" 099 0 "-" "-"';
        self::assertNull($this->parser->parse($bad));
    }

    public function test_returns_null_on_invalid_date(): void
    {
        $line = '127.0.0.1 - - [99/XYZ/9999:99:99:99 +0000] "GET /a HTTP/1.1" 200 0 "-" "-"';
        self::assertNull($this->parser->parse($line));
    }

    public function test_handles_dash_size(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "HEAD /a HTTP/1.1" 304 - "-" "-"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertNull($parsed->responseSize);
        self::assertNull($parsed->referer);
        self::assertSame('HEAD', $parsed->method);
    }

    public function test_handles_url_with_query_string_and_fragment_safe(): void
    {
        $line = '10.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /search?q=hello%20world&page=2 HTTP/1.1" 200 800 "-" "-"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame('/search?q=hello%20world&page=2', $parsed->url);
    }

    public function test_handles_http_2_protocol(): void
    {
        $line = '10.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /api HTTP/2.0" 200 1234 "-" "Mozilla/5.0"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame('/api', $parsed->url);
        self::assertSame(200, $parsed->httpStatus);
    }

    public function test_handles_ipv6_address(): void
    {
        $line = '2001:db8::1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 100 "-" "-"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame('2001:db8::1', $parsed->ip);
    }

    public function test_request_method_uppercased(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "POST /api HTTP/1.1" 201 50 "-" "-"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame('POST', $parsed->method);
    }

    public function test_url_truncated_at_max(): void
    {
        $longUrl = '/' . str_repeat('a', 3000);
        $line = sprintf(
            '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET %s HTTP/1.1" 200 1 "-" "-"',
            $longUrl
        );
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertSame(2048, strlen($parsed->url));
    }

    #[DataProvider('botUserAgentProvider')]
    public function test_detects_known_bots(string $ua, string $expectedName): void
    {
        $line = sprintf(
            '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 1 "-" "%s"',
            $ua
        );
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertTrue($parsed->ua->isBot, "Expected bot for UA: {$ua}");
        self::assertSame($expectedName, $parsed->ua->botName);
    }

    public static function botUserAgentProvider(): array
    {
        return [
            'googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'Googlebot'],
            'yandexbot' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)', 'YandexBot'],
            'bingbot'   => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'Bingbot'],
            'duckduck'  => ['DuckDuckBot/1.1; (+http://duckduckgo.com/duckduckbot.html)', 'DuckDuckBot'],
            'baidu'     => ['Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)', 'Baiduspider'],
            'facebook'  => ['facebookexternalhit/1.1', 'FacebookExternalHit'],
            'generic'   => ['Mozilla/5.0 (compatible; SomeRandomCrawler/1.0)', 'Generic Bot'],
        ];
    }

    public function test_does_not_misclassify_human_browser_as_bot(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 1 "-" '
              .'"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"';
        $parsed = $this->parser->parse($line);

        self::assertNotNull($parsed);
        self::assertFalse($parsed->ua->isBot);
        self::assertNull($parsed->ua->botName);
    }

    public function test_dedup_hashes_match_for_identical_entries(): void
    {
        $line = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /a HTTP/1.1" 200 1 "-" "Mozilla/5.0"';
        $a = $this->parser->parse($line);
        $b = $this->parser->parse($line);

        self::assertNotNull($a);
        self::assertNotNull($b);

        $rowA = $a->toRow(1);
        $rowB = $b->toRow(1);

        self::assertSame($rowA['url_hash'], $rowB['url_hash']);
        self::assertSame($rowA['ua_hash'], $rowB['ua_hash']);
    }

    public function test_dedup_hashes_differ_for_different_urls(): void
    {
        $a = $this->parser->parse('127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /a HTTP/1.1" 200 1 "-" "Mozilla/5.0"');
        $b = $this->parser->parse('127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET /b HTTP/1.1" 200 1 "-" "Mozilla/5.0"');

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotSame($a->toRow()['url_hash'], $b->toRow()['url_hash']);
    }

    public function test_strips_trailing_newline(): void
    {
        $line = "127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] \"GET / HTTP/1.1\" 200 0 \"-\" \"-\"\r\n";
        self::assertNotNull($this->parser->parse($line));
    }

    public function test_empty_referer_normalized_to_null(): void
    {
        $withDash = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 0 "-" "Mozilla/5.0"';
        $withEmpty = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 0 "" "Mozilla/5.0"';
        $withReal = '127.0.0.1 - - [21/Mar/2019:00:20:06 +0300] "GET / HTTP/1.1" 200 0 "https://example.com/" "Mozilla/5.0"';

        $a = $this->parser->parse($withDash);
        $b = $this->parser->parse($withEmpty);
        $c = $this->parser->parse($withReal);

        self::assertNotNull($a);
        self::assertNotNull($b);
        self::assertNotNull($c);

        self::assertNull($a->referer);
        self::assertNull($b->referer);
        self::assertSame('https://example.com/', $c->referer);
    }
}
