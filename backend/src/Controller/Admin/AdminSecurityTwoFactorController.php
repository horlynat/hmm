<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\NotificationPriorityEnum;
use App\Repository\UserRepository;
use App\Security\Voter\SecurityVoter;
use App\Service\AdminAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue d'ensemble de la 2FA (TOTP) sur l'ensemble des comptes, et action de
 * secours pour un admin : forcer la désactivation de la 2FA d'un compte qui
 * a perdu l'accès à son application d'authentification (aucune procédure de
 * self-service n'existe pour ce cas, par nature).
 *
 * L'activation reste toujours en libre-service (TwoFactorController) : un
 * admin ne peut pas activer la 2FA à la place d'un utilisateur, seulement
 * la désactiver.
 *
 * 🔒 Sécurité : réservé à SecurityVoter::MANAGE_2FA (ROLE_ADMIN et plus).
 */
#[Route('/admin/security/2fa', name: 'admin_security_two_factor_')]
class AdminSecurityTwoFactorController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_2FA);

        return $this->render('admin/security/two_factor.html.twig', [
            'users' => $userRepository->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/{id}/disable', name: 'disable', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function disable(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, AdminAlertNotifier $adminAlertNotifier): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(SecurityVoter::MANAGE_2FA, $user);

        if (!$this->isCsrfTokenValid('admin_security_two_factor_disable_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_security_two_factor_index');
        }

        $user->setTotpSecret(null);
        $user->setIsTwoFactorEnabled(false);
        $entityManager->flush();

        $actor = $this->getUser();
        $adminAlertNotifier->alert(
            NotificationPriorityEnum::HIGH,
            '2FA désactivée de force',
            sprintf(
                'La double authentification de %s a été désactivée de force par %s.',
                $user->getEmail(),
                $actor instanceof User ? $actor->getEmail() : 'un administrateur',
            ),
        );

        $this->addFlash('success', sprintf('2FA désactivée de force pour %s.', $user->getEmail()));

        return $this->redirectToRoute('admin_security_two_factor_index');
    }
}
