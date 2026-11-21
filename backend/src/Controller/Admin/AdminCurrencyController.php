<?php

namespace App\Controller\Admin;

use App\Enum\CurrencyEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Préférence d'affichage personnelle (pas une donnée système) : posée en
 * cookie, lisible par n'importe quel utilisateur authentifié du back-office,
 * sans Voter dédié — au pire un utilisateur change sa propre devise
 * d'affichage, aucune donnée n'est modifiée.
 *
 * Audit 2026-11-20 (Info) : ni CSRF ni validation de la cible de
 * redirection — un visiteur authentifié piégé sur une page tierce avec un
 * <form method=post> automatique vers cette route se voyait sa préférence
 * changée (impact faible, mais gratuit à corriger) ET, surtout, le Referer
 * forgé par la page attaquante était réinjecté tel quel comme cible de
 * redirection (open redirect chaîné à la CSRF). Jeton CSRF + même garde-fou
 * anti open-redirect que SelfSessionController/AdminSecuritySessionController
 * (Referer accepté seulement s'il pointe vers cet hôte).
 */
#[Route('/admin/currency', name: 'admin_currency_')]
final class AdminCurrencyController extends AbstractController
{
    #[Route('/set', name: 'set', methods: ['POST'])]
    public function set(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_currency_set', $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_dashboard_index');
        }

        $response = $this->redirectBackOrToDashboard($request);

        $currency = CurrencyEnum::tryFrom((string) $request->request->get('currency'));
        if (null !== $currency) {
            $response->headers->setCookie(
                Cookie::create('display_currency', $currency->value)
                    ->withExpires(strtotime('+1 year'))
                    ->withPath('/')
                    ->withSecure($request->isSecure())
                    ->withHttpOnly(false)
                    ->withSameSite(Cookie::SAMESITE_LAX),
            );
        }

        return $response;
    }

    /**
     * Même garde-fou anti open-redirect que
     * AdminSecuritySessionController::redirectBackOrToSessions() :
     * le Referer n'est suivi que s'il pointe vers cet hôte.
     */
    private function redirectBackOrToDashboard(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if (null !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_dashboard_index');
    }
}
