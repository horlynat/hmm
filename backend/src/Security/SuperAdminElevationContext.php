<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lie l'élévation PAM (ROLE_SUPER_ADMIN temporaire, cf.
 * User::$superAdminElevatedUntil) à la SESSION web qui l'a demandée.
 *
 * Avant : l'élévation vivait uniquement sur l'entité User — pendant 30 min,
 * TOUTES les sessions du compte ET tous ses JWT (lexik recharge l'entité, donc
 * getRoles()) devenaient super-admin. Un jeton API volé, ou une autre session
 * ouverte ailleurs, héritait donc du privilège maximal.
 *
 * Désormais User::isSuperAdminElevated() exige en plus que la requête en
 * cours porte le marqueur de session écrit par PrivilegeElevationController
 * (e-mail + horodatage exact de fin d'élévation). Conséquences :
 * - JWT / firewall API : jamais élevé (aucune session, marqueur jamais lu) ;
 * - autre session du même compte : jamais élevée ;
 * - désactivation ou nouvelle élévation : l'horodatage en base change, tout
 *   ancien marqueur devient caduc ;
 * - CLI / worker Messenger : jamais élevé.
 *
 * État statique (l'entité n'a pas accès aux services) réinitialisé au début
 * de chaque requête principale (SuperAdminElevationSubscriber) et entre deux
 * requêtes d'un worker FrankenPHP (ResetInterface / services_resetter).
 */
final class SuperAdminElevationContext
{
    public const SESSION_KEY = '_pam_super_admin_elevation';

    private static ?string $userIdentifier = null;
    private static ?int $until = null;

    public static function grant(string $userIdentifier, int $until): void
    {
        self::$userIdentifier = $userIdentifier;
        self::$until = $until;
    }

    public static function reset(): void
    {
        self::$userIdentifier = null;
        self::$until = null;
    }

    public static function allows(string $userIdentifier, ?\DateTimeImmutable $elevatedUntil): bool
    {
        return null !== self::$userIdentifier
            && null !== $elevatedUntil
            && self::$userIdentifier === $userIdentifier
            && self::$until === $elevatedUntil->getTimestamp();
    }

    /**
     * Lit le marqueur de la session de la requête — uniquement hors firewall
     * API (stateless) et seulement si une session existe déjà.
     */
    public static function bindFromRequest(Request $request): void
    {
        self::reset();

        if (str_starts_with(rawurldecode($request->getPathInfo()), '/api') || !$request->hasPreviousSession()) {
            return;
        }

        $marker = $request->getSession()->get(self::SESSION_KEY);
        if (\is_array($marker) && \is_string($marker['user'] ?? null) && \is_int($marker['until'] ?? null) && $marker['until'] > time()) {
            self::grant($marker['user'], $marker['until']);
        }
    }
}
