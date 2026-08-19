<?php

namespace App\Tests\Entity;

use App\Entity\BlockedIp;
use PHPUnit\Framework\TestCase;

final class BlockedIpTest extends TestCase
{
    public function testPermanentBlockIsNeverExpired(): void
    {
        $blockedIp = new BlockedIp(ip: '203.0.113.1', reason: 'test', expiresAt: null);

        self::assertFalse($blockedIp->isExpired());
    }

    public function testFutureExpiryIsNotExpired(): void
    {
        $blockedIp = new BlockedIp(
            ip: '203.0.113.1',
            reason: 'test',
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        self::assertFalse($blockedIp->isExpired());
    }

    public function testPastExpiryIsExpired(): void
    {
        $blockedIp = new BlockedIp(
            ip: '203.0.113.1',
            reason: 'test',
            expiresAt: new \DateTimeImmutable('-1 day'),
        );

        self::assertTrue($blockedIp->isExpired());
    }
}
