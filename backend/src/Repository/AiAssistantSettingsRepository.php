<?php

namespace App\Repository;

use App\Entity\AiAssistantSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiAssistantSettings>
 */
class AiAssistantSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAssistantSettings::class);
    }

    /**
     * Retourne la ligne unique de réglages de l'assistant IA, en la créant
     * avec des valeurs vides si elle n'existe pas encore.
     */
    public function getSettings(): AiAssistantSettings
    {
        $settings = $this->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $settings) {
            $settings = new AiAssistantSettings();
            $em = $this->getEntityManager();
            $em->persist($settings);
            $em->flush();
        }

        return $settings;
    }
}
