<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page atterrissage d'un compte admin/collaborateur désactivé ou dont
 * l'email n'est pas vérifié (voir App\EventSubscriber\AccountStatusSubscriber).
 * Rendue en dehors de base.html.twig : la sidebar admin complète n'a pas de
 * sens à afficher à un compte auquel l'accès est justement refusé.
 */
class AdminAccountStatusController extends AbstractController
{
    #[Route('/admin/compte-bloque', name: 'admin_account_blocked', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $reason = 'unverified' === $request->query->get('reason') ? 'unverified' : 'disabled';

        return $this->render('admin/account_blocked.html.twig', ['reason' => $reason]);
    }
}
