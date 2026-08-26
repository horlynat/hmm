<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Résout le bon lien à inclure dans un email selon le rôle du destinataire.
 *
 * Depuis la refonte Next.js de l'espace client/freelance, un utilisateur
 * ROLE_USER/ROLE_EDITOR ne travaille plus jamais dans les pages Symfony
 * historiques (member_project_read, verify_user...) — elles restent
 * d'anciennes routes fonctionnelles mais qui ne correspondent plus à ce
 * qu'il voit réellement. Seul un compte ROLE_ADMIN (minimum) vit encore dans
 * le back-office Symfony (admin_profile_read...) et doit y être renvoyé
 * directement.
 */
final class AccountLinkResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.frontend_url')] private readonly string $frontendUrl,
    ) {
    }

    public function isBackOfficeUser(User $user): bool
    {
        return \in_array($user->getPrimaryRole(), ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true);
    }

    /**
     * @param array<string, mixed> $backendRouteParams
     */
    public function resolve(User $user, string $backendRoute, array $backendRouteParams, string $frontendPath): string
    {
        if ($this->isBackOfficeUser($user)) {
            return $this->urlGenerator->generate($backendRoute, $backendRouteParams, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        // Espace client/freelance non (encore) localisé par utilisateur :
        // le français reste la langue par défaut du site (routing Next.js).
        return rtrim($this->frontendUrl, '/').'/fr'.$frontendPath;
    }

    /**
     * Lien vers un espace invité sans compte (ex : fil de ticket support par
     * jeton) — toujours en français par défaut, même repli que resolve() pour
     * les utilisateurs hors back-office : aucun champ de langue n'existe sur
     * SupportTicket.
     */
    public function resolveGuestLink(string $frontendPath): string
    {
        return rtrim($this->frontendUrl, '/').'/fr'.$frontendPath;
    }
}
