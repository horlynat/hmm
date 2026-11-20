<?php

namespace App\Tests\Service;

use App\Repository\AiAssistantConversationLogRepository;
use App\Service\AiAssistantUsageRecorder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Invariant budgétaire (audit 2026-11-20, P1 #11) : un appel Claude payé est
 * TOUJOURS compté dans le plafond mensuel, même si l'écriture du journal
 * complet échoue.
 */
final class AiAssistantUsageRecorderTest extends KernelTestCase
{
    private const RESULT = ['model' => 'claude-sonnet-5', 'text' => 'x', 'inputTokens' => 1_000_000, 'outputTokens' => 0, 'cacheCreationTokens' => 0, 'cacheReadTokens' => 0];

    private function requestStack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        return $stack;
    }

    public function testCostIsPersistedEvenWhenTheOrmFlushFails(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        $repository = self::getContainer()->get(AiAssistantConversationLogRepository::class);

        $before = $repository->sumCostThisMonth();

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('Data too long for column'));
        $em->method('getConnection')->willReturn($connection);

        (new AiAssistantUsageRecorder($em, $this->requestStack(), new NullLogger(), 's'))
            ->recordCall(self::RESULT, 'fr', 10, 10, 5);

        self::assertEqualsWithDelta($before + 2.0, $repository->sumCostThisMonth(), 0.000001);

        $connection->executeStatement("DELETE FROM ai_assistant_conversation_log WHERE block_reason = 'log_write_failed'");
    }

    public function testOverlongDescriptiveFieldsNoLongerBreakTheWrite(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(AiAssistantConversationLogRepository::class);
        $before = $repository->sumCostThisMonth();

        (new AiAssistantUsageRecorder($em, $this->requestStack(), new NullLogger(), 's'))
            ->recordCall([...self::RESULT, 'model' => str_repeat('m', 300)], str_repeat('x', 50), 10, 10, 5, [], str_repeat('r', 300));

        self::assertEqualsWithDelta($before + 2.0, $repository->sumCostThisMonth(), 0.000001);
        $em->getConnection()->executeStatement('DELETE FROM ai_assistant_conversation_log WHERE locale = :l', ['l' => 'xxxxx']);
    }

    public function testBlockedWriteFailureDoesNotEscape(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException(new \RuntimeException('base indisponible'));

        (new AiAssistantUsageRecorder($em, $this->requestStack(), new NullLogger(), 's'))->recordBlocked('fr', 3, 'feature_disabled');

        $this->addToAssertionCount(1);
    }
}
