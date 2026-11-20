<?php

namespace App\Service;

use App\Entity\AiAssistantConversationLog;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Journal anonymisé ET compteur de coût de TOUS les appels Claude publics
 * (chat, résumé de contenu, qualification de devis) — c'est la somme des
 * `cost_usd` de cette table que lit App\Service\AiAssistantBudgetGuard pour
 * couper l'assistant au plafond mensuel.
 *
 * Invariant de sécurité (audit 2026-11-20, P1 #11) : un appel Claude payé
 * doit TOUJOURS laisser une trace de coût, même si l'écriture du journal
 * complet échoue. Avant, une `locale` de plus de 5 caractères (non
 * contrainte) faisait échouer le flush APRÈS l'appel Claude : la requête
 * coûtait de l'argent sans jamais être comptée, et le plafond ne coupait
 * jamais. D'où :
 * 1. chaînes tronquées aux longueurs de colonnes (cf. setters de l'entité) ;
 * 2. si le flush ORM échoue malgré tout, insertion DBAL minimale (coût,
 *    modèle, date) qui ne dépend ni de l'état de l'EntityManager (fermé
 *    après une exception de flush) ni des champs descriptifs ;
 * 3. si même ça échoue, log CRITICAL (la base est alors indisponible : le
 *    garde-fou budgétaire, qui la lit, l'est aussi).
 */
final class AiAssistantUsageRecorder
{
    // Tarifs (USD / million de tokens, entrée standard) — suivi de budget
    // interne, pas de la facturation exacte. Sonnet 5 en tarif de lancement.
    private const CLAUDE_PRICING = [
        'sonnet' => ['input' => 2.0, 'output' => 10.0],
        'haiku' => ['input' => 1.0, 'output' => 5.0],
    ];
    // Multiplicateurs du cache de prompt (App\Service\ClaudeClient, TTL 1h).
    private const CACHE_WRITE_MULTIPLIER = 2.0;
    private const CACHE_READ_MULTIPLIER = 0.1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.secret%')] private readonly string $appSecret,
    ) {
    }

    /**
     * Requête refusée AVANT tout appel Claude (aucun coût) — une panne
     * d'écriture ici ne doit pas transformer un refus propre en 500.
     */
    public function recordBlocked(string $locale, int $questionLength, string $reason): void
    {
        $log = (new AiAssistantConversationLog())
            ->setIpHash($this->hashIp())
            ->setLocale($locale)
            ->setQuestionLength($questionLength)
            ->setAnswerLength(0)
            ->setBlocked(true)
            ->setBlockReason($reason);

        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('AiAssistantUsageRecorder : journal de refus non enregistré.', ['reason' => $reason, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Appel Claude effectué (donc payé) — le coût est garanti en base, cf. docblock de classe.
     *
     * @param array{model: string, inputTokens: int, outputTokens: int, cacheCreationTokens: int, cacheReadTokens: int} $claudeResult
     * @param int[]                                                                                                       $chunkIds
     */
    public function recordCall(
        array $claudeResult,
        string $locale,
        int $questionLength,
        int $answerLength,
        int $latencyMs,
        array $chunkIds = [],
        ?string $blockReason = null,
    ): void {
        $cost = number_format($this->estimateCost($claudeResult), 6, '.', '');
        $tokens = $claudeResult['inputTokens'] + $claudeResult['outputTokens'] + $claudeResult['cacheCreationTokens'] + $claudeResult['cacheReadTokens'];

        try {
            $log = (new AiAssistantConversationLog())
                ->setIpHash($this->hashIp())
                ->setLocale($locale)
                ->setQuestionLength($questionLength)
                ->setAnswerLength($answerLength)
                ->setChunkIdsUsed($chunkIds)
                ->setModel($claudeResult['model'])
                ->setClaudeTokens($tokens)
                ->setCostUsd($cost)
                ->setLatencyMs($latencyMs)
                ->setBlocked(null !== $blockReason)
                ->setBlockReason($blockReason);

            $this->entityManager->persist($log);
            $this->entityManager->flush();

            return;
        } catch (\Throwable $e) {
            $this->logger->error('AiAssistantUsageRecorder : journal complet non enregistré, repli sur une écriture de coût minimale.', ['error' => $e->getMessage()]);
        }

        try {
            $this->entityManager->getConnection()->insert('ai_assistant_conversation_log', [
                'created_at' => new \DateTimeImmutable(),
                'ip_hash' => $this->hashIp(),
                'locale' => '??',
                'question_length' => 0,
                'answer_length' => 0,
                'chunk_ids_used' => '[]',
                'model' => mb_substr($claudeResult['model'], 0, 50),
                'claude_tokens' => $tokens,
                'cost_usd' => $cost,
                'blocked' => 0,
                'block_reason' => 'log_write_failed',
            ], [
                'created_at' => Types::DATETIME_IMMUTABLE,
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical('AiAssistantUsageRecorder : coût d\'un appel Claude NON comptabilisé (base indisponible).', ['cost_usd' => $cost, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param array{model: string, inputTokens: int, outputTokens: int, cacheCreationTokens: int, cacheReadTokens: int} $claudeResult
     */
    public function estimateCost(array $claudeResult): float
    {
        $tier = str_contains(mb_strtolower($claudeResult['model']), 'haiku') ? 'haiku' : 'sonnet';
        $pricing = self::CLAUDE_PRICING[$tier];

        // Le system prompt est mis en cache côté ClaudeClient : écrit (miss,
        // +100 %) ou relu (hit, -90 %). `inputTokens` = partie non cachée.
        return ($claudeResult['inputTokens'] / 1_000_000) * $pricing['input']
            + ($claudeResult['cacheCreationTokens'] / 1_000_000) * $pricing['input'] * self::CACHE_WRITE_MULTIPLIER
            + ($claudeResult['cacheReadTokens'] / 1_000_000) * $pricing['input'] * self::CACHE_READ_MULTIPLIER
            + ($claudeResult['outputTokens'] / 1_000_000) * $pricing['output'];
    }

    private function hashIp(): string
    {
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';

        return hash('sha256', $ip.$this->appSecret);
    }
}
