<?php

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Délai progressif anti-bruteforce des connexions par mot de passe (P1 #10),
 * en remplacement du verrou dur de compte (User::$lockedUntil posé après 5
 * échecs, réarmé indéfiniment : 1 essai / 3 min suffisait à bloquer n'importe
 * quel compte, super-admin compris — et pendant le verrou, la réponse
 * différait selon que le mot de passe était bon ou non).
 *
 * Deux compteurs, tous deux appliqués AVANT la vérification du mot de passe
 * (même réponse quel que soit le mot de passe → aucun oracle) :
 *
 * 1. Paire (e-mail, IP) : 3 échecs gratuits, puis attente de 30 s doublée à
 *    chaque nouvel échec, plafonnée à 15 min. Un attaquant isolé est ralenti
 *    à quelques essais par heure ; l'IP d'un autre utilisateur n'est jamais
 *    affectée.
 *
 * 2. E-mail, toutes IP confondues (force brute distribuée) : 10 échecs
 *    gratuits, puis même progression. Ce compteur NE S'APPLIQUE PAS aux IP
 *    depuis lesquelles ce compte s'est déjà connecté avec succès (90 jours) :
 *    un attaquant distribué ne peut donc pas bloquer le titulaire légitime
 *    depuis son réseau habituel (atténuation du DoS ciblé).
 *
 * Les tentatives refusées par le délai ne sont PAS comptées (pas de
 * réarmement perpétuel). Un e-mail inconnu est compté exactement comme un
 * compte existant (pas d'énumération par le comportement du délai).
 *
 * Stockage : pool cache.login_throttle (base de données, table cache_items) —
 * indépendant de la persistance du journal FailedLoginAttempt : un échec
 * d'écriture du journal n'empêche jamais le délai.
 */
final class LoginThrottle
{
    public const PAIR_FREE_FAILURES = 3;
    public const GLOBAL_FREE_FAILURES = 10;
    public const BASE_DELAY_SECONDS = 30;
    public const MAX_DELAY_SECONDS = 900;
    private const COUNTER_TTL = 3600;
    private const KNOWN_IP_TTL = 7776000; // 90 jours

    public function __construct(
        #[Autowire(service: 'cache.login_throttle')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return int secondes à attendre avant la prochaine tentative (0 = autorisée)
     */
    public function retryAfter(string $email, ?string $ip, ?int $now = null): int
    {
        $now ??= time();
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);

        $wait = $this->remaining($this->read($this->pairKey($email, $ip)), self::PAIR_FREE_FAILURES, $now);

        if (!$this->cache->getItem($this->knownIpKey($email, $ip))->isHit()) {
            $wait = max($wait, $this->remaining($this->read($this->emailKey($email)), self::GLOBAL_FREE_FAILURES, $now));
        }

        return $wait;
    }

    public function recordFailure(string $email, ?string $ip, ?int $now = null): void
    {
        $now ??= time();
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);

        foreach ([$this->pairKey($email, $ip), $this->emailKey($email)] as $key) {
            $state = $this->read($key);
            $item = $this->cache->getItem($key);
            $item->set(['n' => $state['n'] + 1, 'last' => $now]);
            $item->expiresAfter(self::COUNTER_TTL);
            $this->cache->save($item);
        }
    }

    public function recordSuccess(string $email, ?string $ip): void
    {
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);

        $this->cache->deleteItem($this->pairKey($email, $ip));

        $known = $this->cache->getItem($this->knownIpKey($email, $ip));
        $known->set(true);
        $known->expiresAfter(self::KNOWN_IP_TTL);
        $this->cache->save($known);
    }

    public static function delayFor(int $failures, int $freeFailures): int
    {
        if ($failures < $freeFailures) {
            return 0;
        }

        $exponent = min($failures - $freeFailures, 10);

        return min(self::BASE_DELAY_SECONDS * (2 ** $exponent), self::MAX_DELAY_SECONDS);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_substr(mb_strtolower(trim($email)), 0, 180);
    }

    private static function normalizeIp(?string $ip): string
    {
        return null === $ip || '' === $ip ? 'unknown' : mb_substr($ip, 0, 45);
    }

    /**
     * @param array{n: int, last: int} $state
     */
    private function remaining(array $state, int $freeFailures, int $now): int
    {
        $delay = self::delayFor($state['n'], $freeFailures);

        return 0 === $delay ? 0 : max(0, $state['last'] + $delay - $now);
    }

    /**
     * @return array{n: int, last: int}
     */
    private function read(string $key): array
    {
        $value = $this->cache->getItem($key)->get();

        return \is_array($value) && \is_int($value['n'] ?? null) && \is_int($value['last'] ?? null)
            ? ['n' => $value['n'], 'last' => $value['last']]
            : ['n' => 0, 'last' => 0];
    }

    private function pairKey(string $email, string $ip): string
    {
        return 'p'.hash('sha256', $email."\0".$ip);
    }

    private function emailKey(string $email): string
    {
        return 'e'.hash('sha256', $email);
    }

    private function knownIpKey(string $email, string $ip): string
    {
        return 'k'.hash('sha256', $email."\0".$ip);
    }
}
