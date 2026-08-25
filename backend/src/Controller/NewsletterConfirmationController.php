<?php

namespace App\Controller;

use App\Repository\NewsletterSubscriberRepository;
use App\Service\EmailManager;
use App\Service\JWTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Confirmation d'inscription (double opt-in) et désinscription de la
 * newsletter du blog — cf. App\Entity\NewsletterSubscriber. Contrairement à
 * App\Controller\Api\EmailVerificationController (vérification d'email d'un
 * compte User, qui route via une page Next.js dédiée pour ramener le
 * visiteur dans son espace ensuite), un abonné newsletter n'a aucun compte
 * ni contexte applicatif à retrouver après le clic — le lien mène donc
 * directement ici, une page Symfony autonome (templates/newsletter/
 * result.html.twig), sans aller-retour Next.js superflu pour un affichage
 * aussi ponctuel.
 *
 * Routes publiques SANS préfixe /api (contrairement au reste des endpoints
 * de ce contrôleur) : ce sont des liens cliqués directement depuis un
 * client mail, jamais des appels programmatiques.
 */
final class NewsletterConfirmationController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'app.frontend_url')] private readonly string $frontendUrl,
    ) {
    }

    #[Route('/newsletter/confirm/{token}', name: 'newsletter_confirm', methods: ['GET'])]
    public function confirm(
        string $token,
        JWTService $jwt,
        NewsletterSubscriberRepository $subscriberRepository,
        EntityManagerInterface $entityManager,
        EmailManager $emailManager,
    ): Response {
        try {
            $payload = $jwt->validate($token, 'newsletter_confirmation');
            $subscriber = $subscriberRepository->find($payload['subscriber_id'] ?? 0);
            if (null === $subscriber) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException) {
            return $this->renderResult(
                success: false,
                title: 'Lien invalide ou expiré',
                message: "Ce lien de confirmation n'est plus valide — il a peut-être déjà été utilisé, ou a expiré (7 jours de validité). Réinscrivez-vous depuis le blog pour recevoir un nouveau lien.",
            );
        }

        if ($subscriber->isConfirmed()) {
            return $this->renderResult(
                success: true,
                title: 'Déjà confirmé',
                message: 'Cette adresse était déjà confirmée — rien à faire de plus, vous êtes bien inscrit.',
            );
        }

        $subscriber->confirm();
        // Une désinscription passée n'empêche pas de confirmer un nouveau lien
        // reçu après ré-inscription (cf. NewsletterSubscriberCreateProcessor::
        // resubscribe()) — mais si ce lien précis provient d'un envoi
        // antérieur à une désinscription entre-temps, on respecte ce choix
        // plus récent plutôt que de le rouvrir silencieusement.
        $entityManager->flush();

        $emailManager->sendAsync(
            to: $subscriber->getEmail(),
            subject: 'en' === $subscriber->getLocale() ? 'Welcome to the newsletter' : 'Bienvenue dans la newsletter',
            template: 'newsletter_welcome',
            context: [
                'locale' => $subscriber->getLocale(),
                'blogUrl' => rtrim($this->frontendUrl, '/').'/'.('en' === $subscriber->getLocale() ? 'en' : 'fr').'/blog',
                'unsubscribeUrl' => $this->generateUrl(
                    'newsletter_unsubscribe',
                    ['token' => $jwt->generateNewsletterUnsubscribeToken((int) $subscriber->getId())],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ],
        );

        return $this->renderResult(
            success: true,
            title: 'Inscription confirmée',
            message: 'Merci ! Vous recevrez désormais un e-mail à chaque nouvel article ou projet publié.',
        );
    }

    #[Route('/newsletter/unsubscribe/{token}', name: 'newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        JWTService $jwt,
        NewsletterSubscriberRepository $subscriberRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        try {
            $payload = $jwt->validate($token, 'newsletter_unsubscribe');
            $subscriber = $subscriberRepository->find($payload['subscriber_id'] ?? 0);
            if (null === $subscriber) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException) {
            return $this->renderResult(
                success: false,
                title: 'Lien invalide',
                message: "Ce lien de désinscription n'est pas valide.",
            );
        }

        if (null === $subscriber->getUnsubscribedAt()) {
            $subscriber->unsubscribe();
            $entityManager->flush();
        }

        return $this->renderResult(
            success: true,
            title: 'Désinscription effectuée',
            message: "Vous ne recevrez plus d'e-mails de la newsletter. Vous pouvez vous réinscrire à tout moment depuis le blog.",
        );
    }

    private function renderResult(bool $success, string $title, string $message): Response
    {
        return $this->render('newsletter/result.html.twig', [
            'success' => $success,
            'title' => $title,
            'message' => $message,
            'homeUrl' => rtrim($this->frontendUrl, '/').'/fr',
        ]);
    }
}
