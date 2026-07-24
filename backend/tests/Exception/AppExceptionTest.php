<?php

namespace App\Tests\Exception;

use App\Exception\AppException;
use PHPUnit\Framework\TestCase;

final class AppExceptionTest extends TestCase
{
    public function testHttpStatusCodeIsConsistentAcrossAllInterfaces(): void
    {
        $exception = $this->createException('Message de test', ['id' => 42]);

        $this->assertSame(422, $exception->getHttpStatusCode());
        $this->assertSame(422, $exception->getStatusCode());
        $this->assertSame(422, $exception->getStatus());
        $this->assertSame(422, $exception->getCode());
    }

    public function testContextRoundtrips(): void
    {
        $exception = $this->createException('Message de test', ['userId' => 7, 'email' => 'a@b.c']);

        $this->assertSame(['userId' => 7, 'email' => 'a@b.c'], $exception->getContext());
    }

    public function testMessageAndDetailMatch(): void
    {
        $exception = $this->createException('Une erreur métier précise.');

        $this->assertSame('Une erreur métier précise.', $exception->getMessage());
        $this->assertSame('Une erreur métier précise.', $exception->getDetail());
    }

    public function testDoesNotNotifyByDefault(): void
    {
        $exception = $this->createException('Message de test');

        $this->assertFalse($exception->shouldNotify());
    }

    public function testHeadersAreEmptyByDefault(): void
    {
        $exception = $this->createException('Message de test');

        $this->assertSame([], $exception->getHeaders());
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createException(string $message, array $context = []): AppException
    {
        return new class($message, $context) extends AppException {
            public function getHttpStatusCode(): int
            {
                return 422;
            }

            public function getTitle(): string
            {
                return 'Exception de test';
            }
        };
    }
}
