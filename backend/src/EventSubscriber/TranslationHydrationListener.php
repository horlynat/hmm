<?php

namespace App\EventSubscriber;

use App\Entity\AboutContent;
use App\Entity\Article;
use App\Entity\HomeContent;
use App\Entity\Project;
use App\Repository\TranslationRepository;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

/**
 * Peuple les propriétés "xxxEn" (transitoires, non mappées Doctrine depuis
 * la migration Version20260824000000) des 4 entités bilingues juste après
 * leur chargement, à partir de la table `translation` — cf. App\Repository\
 * TranslationRepository::hydrateEntity().
 *
 * Pas besoin de `setOriginalEntityProperty()` ici (contrairement à
 * TotpSecretEncryptionListener, qui rétablit une vraie colonne mappée) :
 * les propriétés "xxxEn" ne sont plus des colonnes Doctrine du tout, donc
 * invisibles au suivi de changements — les renseigner ici ne les rend pas
 * "sales" pour un flush ultérieur.
 */
final class TranslationHydrationListener implements EventSubscriber
{
    public function __construct(private readonly TranslationRepository $translationRepository)
    {
    }

    /** @return string[] */
    public function getSubscribedEvents(): array
    {
        return [Events::postLoad];
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if (
            $entity instanceof HomeContent
            || $entity instanceof AboutContent
            || $entity instanceof Project
            || $entity instanceof Article
        ) {
            $this->translationRepository->hydrateEntity($entity);
        }
    }
}
