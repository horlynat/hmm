<?php

namespace App\Controller\Admin;

use App\Enum\NotificationPriorityEnum;
use App\Repository\NotificationPreferenceRepository;
use App\Security\Voter\SettingsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pilotage des canaux de notification par niveau d'importance (urgent, haute,
 * moyenne, basse — cf. config/packages/notifier.yaml). Consommé en temps réel
 * par App\Notifier\DatabaseChannelPolicy, qui remplace la policy statique du
 * Notifier Symfony.
 *
 * Canal push : transport ntfy (voir NTFY_DSN dans .env et
 * config/packages/notifier.yaml). Contrairement au reste du back-office, ce
 * transport est configuré au niveau du déploiement (variable d'environnement),
 * pas depuis cette page — l'admin ne fait qu'activer/désactiver son usage par
 * niveau d'importance.
 *
 * 🔒 Sécurité : réservé à SettingsVoter (ROLE_ADMIN et plus).
 */
#[Route('/admin/notification', name: 'admin_notification_')]
class AdminNotificationController extends AbstractController
{
    private const CHANNELS = ['email', 'push'];

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(NotificationPreferenceRepository $notificationPreferenceRepository): Response
    {
        $this->denyAccessUnlessGranted(SettingsVoter::VIEW_NOTIFICATIONS);

        return $this->render('admin/notification/index.html.twig', [
            'preferences' => $notificationPreferenceRepository->ensureDefaults(),
        ]);
    }

    #[Route('/{priority}/toggle/{channel}', name: 'toggle', methods: ['POST'])]
    public function toggle(
        string $priority,
        string $channel,
        Request $request,
        NotificationPreferenceRepository $notificationPreferenceRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::MANAGE_NOTIFICATIONS);

        $priorityEnum = NotificationPriorityEnum::tryFrom($priority);
        if (null === $priorityEnum || !\in_array($channel, self::CHANNELS, true)) {
            throw $this->createNotFoundException('Niveau d\'importance ou canal inconnu.');
        }

        if (!$this->isCsrfTokenValid('admin_notification_toggle_' . $priority . '_' . $channel, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide. Action annulée.');

            return $this->redirectToRoute('admin_notification_index');
        }

        $preferences = $notificationPreferenceRepository->ensureDefaults();
        $preference = $preferences[$priority];

        $enabled = match ($channel) {
            'email' => $preference->setEmailEnabled(!$preference->isEmailEnabled())->isEmailEnabled(),
            'push' => $preference->setPushEnabled(!$preference->isPushEnabled())->isPushEnabled(),
        };
        $preference->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Notifications %s "%s" %s.',
            'push' === $channel ? 'push' : 'e-mail',
            $priorityEnum->getLabel(),
            $enabled ? 'activées' : 'désactivées',
        ));

        return $this->redirectToRoute('admin_notification_index');
    }
}
