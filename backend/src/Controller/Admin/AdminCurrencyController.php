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
 */
#[Route('/admin/currency', name: 'admin_currency_')]
final class AdminCurrencyController extends AbstractController
{
    #[Route('/set', name: 'set', methods: ['POST'])]
    public function set(Request $request): Response
    {
        $referer = $request->headers->get('referer') ?? $this->generateUrl('admin_dashboard_index');
        $response = $this->redirect($referer);

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
}
