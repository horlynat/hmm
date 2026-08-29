<?php

namespace App\Tests\Service;

use App\Service\SensitiveDataScrubber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SensitiveDataScrubberTest extends TestCase
{
    private SensitiveDataScrubber $scrubber;

    protected function setUp(): void
    {
        $this->scrubber = new SensitiveDataScrubber();
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2?: string}>
     */
    public static function cases(): iterable
    {
        yield 'DSN userinfo' => [
            'failed: mysql://app:S3cr3t@db:3306/app?charset=utf8',
            'S3cr3t',
            'mysql://app:***@db:3306/app',
        ];
        yield 'DSN without user' => [
            'redis://:topsecret@cache:6379 unreachable',
            'topsecret',
        ];
        yield 'password=' => ['connect params password=hunter2 dbname=app', 'hunter2'];
        yield 'api_key =>' => ['api_key => "sk-live-abcdef123" rejected', 'sk-live-abcdef123'];
        yield 'Authorization header' => [
            'GET /x 401 Authorization: Bearer eyJhbGc.payload-part.sig next',
            'eyJhbGc.payload-part.sig',
        ];
    }

    #[DataProvider('cases')]
    public function testRedactsSecret(string $input, string $secret, ?string $expectedFragment = null): void
    {
        $out = $this->scrubber->scrub($input);

        self::assertStringNotContainsString($secret, $out);
        self::assertStringContainsString('***', $out);

        if (null !== $expectedFragment) {
            self::assertStringContainsString($expectedFragment, $out);
        }
    }

    public function testLeavesInnocuousTextUntouched(): void
    {
        $text = 'Connection refused to host db.internal:3306 for user app';

        self::assertSame($text, $this->scrubber->scrub($text));
    }
}
