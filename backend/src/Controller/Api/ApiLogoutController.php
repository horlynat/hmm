<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\BlockedTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déconnexion API (P1 #8) — CONTRAT avec le frontend : `POST /api/logout`
 * avec le JWT courant en Bearer → 204 sans corps ; sans JWT valide → 401.
 *
 * Avant : la « déconnexion » du frontend ne faisait que supprimer son cookie,
 * le jeton restait utilisable jusqu'à son expiration par quiconque l'avait
 * intercepté. Désormais le jeton présenté est inscrit dans la liste de
 * blocage lexik (par `jti`, jusqu'à son `exp`, stockage en base) et refusé à
 * toute requête suivante. Un jeton antérieur à l'ajout du `jti` ne peut pas
 * être bloqué individuellement : on révoque alors tous les JWT du compte
 * (User::$tokenVersion).
 *
 * N'agit que sur le jeton présenté : les autres appareils restent connectés
 * (la révocation globale relève du changement de mot de passe ou de la
 * déconnexion forcée).
 */
final class ApiLogoutController extends AbstractController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(
        Request $request,
        JWTTokenManagerInterface $jwtManager,
        BlockedTokenManagerInterface $blockedTokenManager,
        #[Autowire(service: 'lexik_jwt_authentication.extractor.chain_extractor')]
        TokenExtractorInterface $tokenExtractor,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        $rawToken = $tokenExtractor->extract($request);
        if (!$user instanceof User || !\is_string($rawToken)) {
            return new JsonResponse(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $jwtManager->parse($rawToken);
        } catch (JWTDecodeFailureException) {
            return new JsonResponse(['detail' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if (isset($payload['jti'], $payload['exp'])) {
            $blockedTokenManager->add($payload);
        } else {
            $user->bumpTokenVersion();
            $entityManager->flush();
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
