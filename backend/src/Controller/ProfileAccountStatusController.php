<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page d'atterrissage d'un utilisateur/collaborateur désactivé ou dont
 * l'email n'est pas vérifié (voir App\EventSubscriber\AccountStatusSubscriber).
 * Rendue en dehors de base.html.twig, pour les mêmes raisons que
 * App\Controller\Admin\AdminAccountStatusController.
 */
class ProfileAccountStatusController extends AbstractController
{
    #[Route('/profile/compte-bloque', name: 'profile_account_blocked', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $reason = 'unverified' === $request->query->get('reason') ? 'unverified' : 'disabled';

        return $this->render('profile/account_blocked.html.twig', ['reason' => $reason]);
    }
}
