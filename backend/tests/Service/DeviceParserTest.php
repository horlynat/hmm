<?php

namespace App\Tests\Service;

use App\Service\DeviceParser;
use PHPUnit\Framework\TestCase;

final class DeviceParserTest extends TestCase
{
    public function testParsesIphone(): void
    {
        $result = (new DeviceParser())->parse(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1',
        );

        self::assertSame('Smartphone', $result['type']);
        self::assertSame('Apple', $result['brand']);
        self::assertSame('iOS', $result['os']);
        self::assertFalse($result['isBot']);
    }

    public function testParsesDesktopChrome(): void
    {
        $result = (new DeviceParser())->parse(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        );

        self::assertSame('Ordinateur', $result['type']);
        self::assertNull($result['brand']);
        self::assertSame('Windows', $result['os']);
        self::assertSame('Chrome', $result['browser']);
    }

    public function testDetectsBot(): void
    {
        $result = (new DeviceParser())->parse(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        );

        self::assertTrue($result['isBot']);
        self::assertStringContainsString('Robot', $result['label']);
    }

    public function testReturnsUnknownForEmptyUserAgent(): void
    {
        self::assertSame('Inconnu', (new DeviceParser())->parse(null)['type']);
        self::assertSame('Inconnu', (new DeviceParser())->parse('')['type']);
    }
}
