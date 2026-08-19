<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserSessionRepository;
use App\Service\UserSessionRevoker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Self-service : n'importe quel utilisateur connecté peut voir et révoquer
 * SES PROPRES sessions (jamais celles d'un tiers) — contrairement à
 * AdminSecuritySessionController, aucun rôle particulier requis au-delà
 * d'être authentifié : on ne peut jamais faire de mal qu'à soi-même ici.
 * Alimente le bloc "Mes appareils connectés" de member/profile/read.html.twig
 * et admin/profile/read.html.twig (uniquement quand on consulte SON PROPRE
 * profil, cf. ces deux templates).
 */
#[Route('/mes-sessions', name: 'self_session_')]
#[IsGranted('ROLE_USER')]
class SelfSessionController extends AbstractController
{
    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revoke(
        int $id,
        Request $request,
        UserSessionRepository $userSessionRepository,
        UserSessionRevoker $userSessionRevoker,
        EntityManagerInterface $entityManager,
    ): Response {
        $userSession = $userSessionRepository->find($id);
        if (!$userSession) {
            throw $this->createNotFoundException();
        }

        if ($userSession->getUser() !== $this->currentUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($userSession->getSessionId() === $request->getSession()->getId()) {
            $this->addFlash('error', 'Impossible de déconnecter la session que vous utilisez actuellement — utilisez le lien de déconnexion habituel.');

            return $this->redirectBack($request);
        }

        if (!$this->isCsrfTokenValid('self_session_revoke_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectBack($request);
        }

        // Déconnexion complète (pas killLiveSession) : révoquer son propre
        // vieil appareil doit vraiment le déconnecter, remember-me compris —
        // même sémantique qu'une révocation admin, juste sans notification par
        // email (redondant : c'est la personne elle-même qui vient d'agir).
        $userSessionRevoker->forceLogout($userSession);
        $entityManager->flush();

        $this->addFlash('success', 'Session déconnectée.');

        return $this->redirectBack($request);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if (null !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('member_profile_read');
    }
}
