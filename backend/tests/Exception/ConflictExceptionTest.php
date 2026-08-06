<?php

namespace App\Tests\Exception;

use App\Exception\ConflictException;
use PHPUnit\Framework\TestCase;

final class ConflictExceptionTest extends TestCase
{
    public function testExposes409(): void
    {
        $exception = new ConflictException('Un compte existe déjà avec cet email.');

        $this->assertSame(409, $exception->getHttpStatusCode());
        $this->assertSame(409, $exception->getStatusCode());
        $this->assertSame('Conflit de ressource', $exception->getTitle());
    }

    public function testKeepsThePreviousExceptionAndContext(): void
    {
        $previous = new \RuntimeException('unique constraint violation');

        $exception = new ConflictException(
            'Un compte existe déjà avec cet email.',
            context: ['email' => 'test@example.com'],
            previous: $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(['email' => 'test@example.com'], $exception->getContext());
    }
}
