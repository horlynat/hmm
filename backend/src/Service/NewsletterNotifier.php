<?php

namespace App\Service;

use App\Repository\NewsletterSubscriberRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Notifie les abonnés newsletter confirmés (double opt-in, cf. App\Entity\
 * NewsletterSubscriber) qu'un nouvel article ou projet vient d'être publié —
 * appelé depuis AdminArticleController::create() et AdminProjectController::
 * create() (uniquement quand un ProjectInfo — le contenu vitrine public —
 * est réellement renseigné : un projet interne de gestion freelance/client
 * n'en a pas et ne doit jamais déclencher un envoi public, cf. commentaire
 * sur son appel).
 *
 * Chaque envoi passe par EmailManager::sendAsync() (file Messenger, déjà
 * routée pour App\Message\SendEmail) — jamais bloquant sur la requête admin
 * qui vient de créer le contenu, et un abonné dont l'adresse échoue
 * n'affecte pas les autres (un message par destinataire, pas un envoi groupé).
 */
final class NewsletterNotifier
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $subscriberRepository,
        private readonly EmailManager $emailManager,
        private readonly JWTService $jwt,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.frontend_url')] private readonly string $frontendUrl,
    ) {
    }

    /**
     * @param 'article'|'project' $contentType
     */
    public function notifyNewContent(string $title, string $contentType, string $slug): void
    {
        $subscribers = $this->subscriberRepository->findActiveConfirmed();
        if ([] === $subscribers) {
            return;
        }

        $isProject = 'project' === $contentType;
        $path = $isProject ? 'realisations' : 'blog';

        foreach ($subscribers as $subscriber) {
            $locale = 'en' === $subscriber->getLocale() ? 'en' : 'fr';
            $contentUrl = rtrim($this->frontendUrl, '/')."/{$locale}/{$path}/{$slug}";
            $unsubscribeUrl = $this->urlGenerator->generate(
                'newsletter_unsubscribe',
                ['token' => $this->jwt->generateNewsletterUnsubscribeToken((int) $subscriber->getId())],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $subject = 'en' === $locale
                ? ($isProject ? 'New project on the portfolio' : 'New article on the blog')
                : ($isProject ? 'Nouveau projet sur le portfolio' : 'Nouvel article sur le blog');

            $this->emailManager->sendAsync(
                to: $subscriber->getEmail(),
                subject: $subject,
                template: 'newsletter_new_content',
                context: [
                    'locale' => $locale,
                    'isProject' => $isProject,
                    'contentTitle' => $title,
                    'contentUrl' => $contentUrl,
                    'unsubscribeUrl' => $unsubscribeUrl,
                ],
            );
        }
    }
}
