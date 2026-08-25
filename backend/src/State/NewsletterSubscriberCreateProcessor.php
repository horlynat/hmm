<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\NewsletterSubscriberApiResource;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\EmailManager;
use App\Service\JWTService;
use App\Service\PublicSubmissionThrottler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * NewsletterSubscriberApiResource n'étant pas lui-même mappé Doctrine (seul
 * son parent NewsletterSubscriber l'est), le PersistProcessor générique
 * d'API Platform ne le reconnaît pas comme une entité gérable — même
 * situation que ContactMessageCreateProcessor, cf. son docblock pour le
 * détail. On construit donc explicitement un vrai NewsletterSubscriber.
 *
 * Doublon vérifié explicitement ici, pas via #[UniqueEntity] sur l'entité
 * (délibérément absent — cf. docblock de NewsletterSubscriber : cette
 * constraint valide `get_class($value)`, ici NewsletterSubscriberApiResource,
 * une classe non mappée Doctrine, ce qui fait échouer TOUTE requête avec un
 * 500, testé en pratique). Un e-mail déjà inscrit ne renvoie pas une erreur :
 * re-soumettre son e-mail est un geste normal (formulaire rouvert, double
 * clic...), pas une faute du visiteur — on confirme simplement l'inscription
 * existante, idempotent.
 *
 * Double opt-in : une nouvelle inscription déclenche un e-mail de
 * confirmation (lien signé JWT, cf. App\Service\JWTService::
 * generateNewsletterConfirmationToken()) — envoyé en synchrone (sendNow, pas
 * sendAsync) comme App\Controller\Api\EmailVerificationController::resend()
 * pour le même type d'e-mail critique côté User : c'est la seule action de
 * ce flux où le visiteur attend un résultat immédiat ("un e-mail vient de
 * partir"), pas un envoi qu'on peut se permettre de retarder derrière la
 * file Messenger. Aucune notification tant que le lien n'est pas cliqué
 * (cf. NewsletterConfirmationController::confirm(), qui envoie ensuite
 * l'e-mail de bienvenue).
 */
final class NewsletterSubscriberCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NewsletterSubscriberRepository $subscriberRepository,
        private readonly PublicSubmissionThrottler $throttler,
        private readonly JWTService $jwt,
        private readonly EmailManager $emailManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NewsletterSubscriber
    {
        \assert($data instanceof NewsletterSubscriberApiResource);

        // Même compteur que les autres formulaires publics (contact, devis,
        // témoignage) — cf. docblock de PublicSubmissionThrottler, qui liste
        // déjà "inscription" parmi les flux visés.
        $this->throttler->assertFormSubmissionAllowed();

        $email = mb_strtolower(trim($data->getEmail()));
        $existing = $this->subscriberRepository->findOneBy(['email' => $email]);
        if (null !== $existing) {
            // Ré-abonnement implicite si l'e-mail s'était désinscrit —
            // re-soumettre le formulaire est un signal de consentement clair.
            if (null !== $existing->getUnsubscribedAt()) {
                $existing->resubscribe();
                $this->entityManager->flush();
            }

            return $existing;
        }

        $entity = new NewsletterSubscriber();
        $entity->setEmail($email);
        $locale = 'en' === $data->getLocale() ? 'en' : 'fr';
        $entity->setLocale($locale);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $confirmUrl = $this->urlGenerator->generate(
            'newsletter_confirm',
            ['token' => $this->jwt->generateNewsletterConfirmationToken((int) $entity->getId())],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $unsubscribeUrl = $this->urlGenerator->generate(
            'newsletter_unsubscribe',
            ['token' => $this->jwt->generateNewsletterUnsubscribeToken((int) $entity->getId())],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->emailManager->sendNow(
            to: $entity->getEmail(),
            subject: 'en' === $locale ? 'Confirm your newsletter subscription' : 'Confirmez votre inscription à la newsletter',
            template: 'newsletter_confirmation',
            context: [
                'locale' => $locale,
                'confirmUrl' => $confirmUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );

        return $entity;
    }
}
